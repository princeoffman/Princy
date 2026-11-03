import uuid
from datetime import datetime, timedelta

from django.conf import settings
from django.core.exceptions import ValidationError
from django.db import models
from django.db.models import F, Q
from django.utils import timezone


APPOINTMENT_DURATION = timedelta(minutes=60)
BOOKING_WINDOW_DAYS = 30


def generate_booking_reference():
    return f'APT-{uuid.uuid4().hex[:12].upper()}'


class Department(models.Model):
    name = models.CharField(max_length=100, unique=True)
    description = models.TextField(blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ['name']

    def __str__(self):
        return self.name


class WeeklySchedule(models.Model):
    WEEKDAYS = [
        (0, 'Monday'),
        (1, 'Tuesday'),
        (2, 'Wednesday'),
        (3, 'Thursday'),
        (4, 'Friday'),
        (5, 'Saturday'),
        (6, 'Sunday'),
    ]

    weekday = models.PositiveSmallIntegerField(choices=WEEKDAYS, unique=True)
    opening_time = models.TimeField()
    closing_time = models.TimeField()
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ['weekday']
        constraints = [
            models.CheckConstraint(
                condition=Q(closing_time__gt=F('opening_time')),
                name='schedule_closes_after_opening',
            ),
        ]

    def clean(self):
        super().clean()
        if self.opening_time and self.closing_time and self.closing_time <= self.opening_time:
            raise ValidationError({'closing_time': 'Closing time must be after opening time.'})

    def __str__(self):
        return self.get_weekday_display()


class UnavailableDate(models.Model):
    date = models.DateField(unique=True)
    reason = models.CharField(max_length=255, blank=True)

    class Meta:
        ordering = ['date']
        verbose_name = 'unavailable date'
        verbose_name_plural = 'unavailable dates'

    def __str__(self):
        return str(self.date)


class Appointment(models.Model):
    class Gender(models.TextChoices):
        FEMALE = 'female', 'Female'
        MALE = 'male', 'Male'
        NON_BINARY = 'non_binary', 'Non-binary'
        PREFER_NOT_TO_SAY = 'prefer_not_to_say', 'Prefer not to say'

    class Status(models.TextChoices):
        CONFIRMED = 'confirmed', 'Confirmed'
        CANCELLED = 'cancelled', 'Cancelled'

    first_name = models.CharField(max_length=100)
    last_name = models.CharField(max_length=100)
    date_of_birth = models.DateField()
    gender = models.CharField(max_length=20, choices=Gender.choices)
    email = models.EmailField()
    phone_number = models.CharField(max_length=30)
    street_address = models.CharField(max_length=255)
    address_line_2 = models.CharField(max_length=255, blank=True, default='')
    city = models.CharField(max_length=100)
    state_province = models.CharField(max_length=100)
    postal_code = models.CharField(max_length=20, blank=True, default='')
    country = models.CharField(max_length=100)
    department = models.ForeignKey(Department, on_delete=models.PROTECT, related_name='appointments')
    requested_procedure = models.CharField(max_length=255)
    appointment_date = models.DateField()
    start_time = models.TimeField()
    end_time = models.TimeField()
    comments = models.TextField(blank=True)
    booking_reference = models.CharField(
        max_length=16,
        unique=True,
        default=generate_booking_reference,
        editable=False,
    )
    status = models.CharField(max_length=10, choices=Status.choices, default=Status.CONFIRMED)
    created_at = models.DateTimeField(auto_now_add=True)
    patient = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        related_name='patient_appointments',
        blank=True,
        null=True,
    )

    class Meta:
        ordering = ['appointment_date', 'start_time']
        constraints = [
            models.CheckConstraint(
                condition=Q(end_time__gt=F('start_time')),
                name='appointment_ends_after_start',
            ),
            models.UniqueConstraint(
                fields=['appointment_date', 'start_time'],
                condition=Q(status='confirmed'),
                name='unique_confirmed_appointment_start',
            ),
        ]

    def clean(self):
        super().clean()
        errors = {}
        today = timezone.localdate()
        last_booking_date = today + timedelta(days=BOOKING_WINDOW_DAYS)

        if self.date_of_birth and self.date_of_birth > today:
            errors['date_of_birth'] = 'Date of birth cannot be in the future.'

        if self.appointment_date and self.status == self.Status.CONFIRMED:
            if not today <= self.appointment_date <= last_booking_date:
                errors['appointment_date'] = (
                    f'Choose a date from {today} through {last_booking_date}.'
                )
            elif self.appointment_date == today and self.start_time:
                current_time = timezone.localtime().time().replace(tzinfo=None)
                if self.start_time <= current_time:
                    errors['start_time'] = 'Choose a time later than the current local time.'

            if self.start_time and self.end_time and (
                datetime.combine(self.appointment_date, self.end_time)
                - datetime.combine(self.appointment_date, self.start_time)
                != APPOINTMENT_DURATION
            ):
                errors['end_time'] = 'Appointments must be exactly 60 minutes long.'

            if self.appointment_date and self.start_time and self.end_time:
                schedule = WeeklySchedule.objects.filter(
                    weekday=self.appointment_date.weekday(),
                    is_active=True,
                ).first()
                if (
                    schedule is None
                    or self.start_time < schedule.opening_time
                    or self.end_time > schedule.closing_time
                ):
                    errors['start_time'] = 'The appointment must be within opening hours.'
                elif self.start_time and schedule:
                    offset = (
                        datetime.combine(self.appointment_date, self.start_time)
                        - datetime.combine(self.appointment_date, schedule.opening_time)
                    )
                    if offset.total_seconds() % APPOINTMENT_DURATION.total_seconds():
                        errors['start_time'] = 'Choose a time offered by the weekly schedule.'

                if UnavailableDate.objects.filter(date=self.appointment_date).exists():
                    errors['appointment_date'] = 'The clinic is unavailable on this date.'

            if (
                self._state.adding
                and self.department_id
                and Department.objects.filter(
                    pk=self.department_id,
                    is_active=False,
                ).exists()
            ):
                errors['department'] = 'Choose an active department.'

        if self.status == self.Status.CONFIRMED and self.appointment_date and self.start_time and self.end_time:
            overlapping = Appointment.objects.filter(
                appointment_date=self.appointment_date,
                status=self.Status.CONFIRMED,
                start_time__lt=self.end_time,
                end_time__gt=self.start_time,
            )
            if self.pk:
                overlapping = overlapping.exclude(pk=self.pk)
            if overlapping.exists():
                errors['start_time'] = 'This time overlaps an existing confirmed appointment.'

        if errors:
            raise ValidationError(errors)

    def __str__(self):
        return f'{self.booking_reference} — {self.first_name} {self.last_name}'
