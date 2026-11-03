import logging

from django.conf import settings
from django.core.mail import send_mail


logger = logging.getLogger(__name__)


def send_booking_notifications(appointment):
    """Send configured booking messages without changing the saved booking result."""
    mailer = settings.MAILERS.get('default', {})
    if (
        mailer.get('BACKEND') != 'django.core.mail.backends.smtp.EmailBackend'
        or not mailer.get('HOST')
        or not settings.DEFAULT_FROM_EMAIL
        or not settings.CLINIC_STAFF_EMAIL
    ):
        logger.info(
            'Booking email delivery is not configured; booking %s remains saved.',
            appointment.booking_reference,
        )
        return

    details = (
        f"Booking reference: {appointment.booking_reference}\n"
        f"Patient: {appointment.first_name} {appointment.last_name}\n"
        f"Department: {appointment.department.name}\n"
        f"Procedure requested: {appointment.requested_procedure}\n"
        f"Date: {appointment.appointment_date.isoformat()} CAT\n"
        f"Time: {appointment.start_time.strftime('%H:%M')}–"
        f"{appointment.end_time.strftime('%H:%M')} CAT\n"
    )
    messages = [
        (
            'Your Pharmaid Clinic appointment is confirmed',
            f"Your appointment has been saved.\n\n{details}",
            [appointment.email],
            'patient confirmation',
        ),
        (
            f'New appointment booking {appointment.booking_reference}',
            f'A patient appointment has been saved.\n\n{details}',
            [settings.CLINIC_STAFF_EMAIL],
            'staff notification',
        ),
    ]

    for subject, body, recipients, message_type in messages:
        try:
            sent_count = send_mail(
                subject,
                body,
                settings.DEFAULT_FROM_EMAIL,
                recipients,
                fail_silently=False,
                using='default',
            )
            if sent_count != 1:
                logger.warning(
                    'Could not deliver %s for booking %s.',
                    message_type,
                    appointment.booking_reference,
                )
        except Exception:
            logger.exception(
                'Could not deliver %s for booking %s; the booking remains saved.',
                message_type,
                appointment.booking_reference,
            )


def send_cancellation_notifications(appointment):
    """Notify the patient and clinic staff about a saved cancellation."""
    mailer = settings.MAILERS.get('default', {})
    if (
        mailer.get('BACKEND') != 'django.core.mail.backends.smtp.EmailBackend'
        or not mailer.get('HOST')
        or not settings.DEFAULT_FROM_EMAIL
        or not settings.CLINIC_STAFF_EMAIL
    ):
        logger.info(
            'Cancellation email delivery is not configured; cancellation %s remains saved.',
            appointment.booking_reference,
        )
        return

    details = (
        f"Booking reference: {appointment.booking_reference}\n"
        f"Patient: {appointment.first_name} {appointment.last_name}\n"
        f"Department: {appointment.department.name}\n"
        f"Date: {appointment.appointment_date.isoformat()} CAT\n"
        f"Time: {appointment.start_time.strftime('%H:%M')}–"
        f"{appointment.end_time.strftime('%H:%M')} CAT\n"
    )
    messages = [
        (
            'Your Pharmaid Clinic appointment was cancelled',
            f'Your appointment cancellation has been saved.\n\n{details}',
            [appointment.email],
            'patient cancellation confirmation',
        ),
        (
            f'Appointment cancellation {appointment.booking_reference}',
            f'A patient appointment has been cancelled.\n\n{details}',
            [settings.CLINIC_STAFF_EMAIL],
            'staff cancellation notification',
        ),
    ]

    for subject, body, recipients, message_type in messages:
        try:
            sent_count = send_mail(
                subject,
                body,
                settings.DEFAULT_FROM_EMAIL,
                recipients,
                fail_silently=False,
                using='default',
            )
            if sent_count != 1:
                logger.warning(
                    'Could not deliver %s for booking %s.',
                    message_type,
                    appointment.booking_reference,
                )
        except Exception:
            logger.exception(
                'Could not deliver %s for booking %s; cancellation remains saved.',
                message_type,
                appointment.booking_reference,
            )
