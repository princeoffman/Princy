from datetime import datetime, timedelta
from time import sleep

from django.core.exceptions import ValidationError
from django.db import IntegrityError, OperationalError, connection, transaction
from django.db.models import F
from django.utils import timezone

from .models import (
    APPOINTMENT_DURATION,
    Appointment,
    UnavailableDate,
    WeeklySchedule,
)

CANCELLATION_NOTICE = timedelta(hours=12)


def get_patient_cancellation_state(appointment, now=None):
    clinic_timezone = timezone.get_current_timezone()
    starts_at = timezone.make_aware(
        datetime.combine(appointment.appointment_date, appointment.start_time),
        clinic_timezone,
    )
    current_time = now or timezone.now()
    if starts_at <= current_time:
        return 'past'
    if starts_at - current_time < CANCELLATION_NOTICE:
        return 'notice_window'
    return 'allowed'


def get_available_slots(department, day):
    """Return one-hour slots calculated from clinic records, never client-supplied times."""
    if not department.is_active:
        return []
    schedule = WeeklySchedule.objects.filter(
        weekday=day.weekday(),
        is_active=True,
    ).first()
    if schedule is None or UnavailableDate.objects.filter(date=day).exists():
        return []

    now = timezone.localtime()
    slots = []
    start = datetime.combine(day, schedule.opening_time)
    closing = datetime.combine(day, schedule.closing_time)
    current_time = now.time().replace(tzinfo=None)

    while start + APPOINTMENT_DURATION <= closing:
        end = start + APPOINTMENT_DURATION
        slot_start = start.time()
        slot_end = end.time()
        if day != now.date() or slot_start > current_time:
            is_booked = Appointment.objects.filter(
                appointment_date=day,
                status=Appointment.Status.CONFIRMED,
                start_time__lt=slot_end,
                end_time__gt=slot_start,
            ).exists()
            if not is_booked:
                slots.append({'start_time': slot_start, 'end_time': slot_end})
        start = end

    return slots


def create_confirmed_appointment(appointment):
    """Validate and save a booking while serializing writes for its weekday schedule."""
    for attempt in range(3):
        try:
            with transaction.atomic():
                schedule = WeeklySchedule.objects.select_for_update().filter(
                    weekday=appointment.appointment_date.weekday(),
                ).first()

                if schedule is not None and connection.vendor == 'sqlite':
                    # SQLite has no row-level SELECT FOR UPDATE; this write takes its writer lock.
                    WeeklySchedule.objects.filter(pk=schedule.pk).update(is_active=F('is_active'))

                appointment.full_clean()
                appointment.save()
                return appointment
        except IntegrityError as error:
            has_conflict = Appointment.objects.filter(
                appointment_date=appointment.appointment_date,
                status=Appointment.Status.CONFIRMED,
                start_time__lt=appointment.end_time,
                end_time__gt=appointment.start_time,
            ).exists()
            if has_conflict:
                raise ValidationError({
                    'start_time': 'This time is no longer available. Please choose another slot.',
                }) from error
            raise
        except OperationalError as error:
            is_sqlite_lock = connection.vendor == 'sqlite' and 'locked' in str(error).lower()
            if not is_sqlite_lock or attempt == 2:
                raise
            sleep(0.05 * (attempt + 1))

    raise RuntimeError('Appointment could not be saved after retrying database locks.')


def cancel_patient_appointment(booking_reference, patient):
    """Cancel only an owned, confirmed booking with at least 12 hours' notice."""
    for attempt in range(3):
        try:
            with transaction.atomic():
                appointment = Appointment.objects.filter(
                    booking_reference=booking_reference,
                    patient=patient,
                ).first()
                if appointment is None:
                    return None, 'not_found'

                schedule = WeeklySchedule.objects.filter(
                    weekday=appointment.appointment_date.weekday(),
                ).first()
                if schedule is not None and connection.vendor == 'sqlite':
                    # Acquire SQLite's writer lock before checking status or cutoff.
                    WeeklySchedule.objects.filter(pk=schedule.pk).update(is_active=F('is_active'))
                elif connection.vendor == 'sqlite':
                    Appointment.objects.filter(pk=appointment.pk).update(status=F('status'))

                appointment = Appointment.objects.select_for_update().filter(
                    pk=appointment.pk,
                    patient=patient,
                ).first()
                if appointment is None:
                    return None, 'not_found'
                if appointment.status != Appointment.Status.CONFIRMED:
                    return appointment, 'not_confirmed'

                cancellation_state = get_patient_cancellation_state(appointment)
                if cancellation_state != 'allowed':
                    return appointment, cancellation_state

                updated = Appointment.objects.filter(
                    pk=appointment.pk,
                    patient=patient,
                    status=Appointment.Status.CONFIRMED,
                ).update(status=Appointment.Status.CANCELLED)
                if updated != 1:
                    return appointment, 'not_confirmed'

                appointment.status = Appointment.Status.CANCELLED
                return appointment, 'cancelled'
        except OperationalError as error:
            is_sqlite_lock = connection.vendor == 'sqlite' and 'locked' in str(error).lower()
            if not is_sqlite_lock or attempt == 2:
                raise
            sleep(0.05 * (attempt + 1))

    raise RuntimeError('Appointment could not be cancelled after retrying database locks.')
