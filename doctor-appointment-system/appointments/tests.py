from datetime import date, datetime, time, timedelta
from concurrent.futures import ThreadPoolExecutor
from threading import Barrier
from unittest.mock import patch

from django.contrib.auth import get_user_model
from django.core.exceptions import ValidationError
from django.conf import settings
from django.db import close_old_connections
from django.test import TestCase, TransactionTestCase, override_settings
from django.utils import timezone
from rest_framework.test import APIClient, APITestCase

from .models import Appointment, Department, UnavailableDate, WeeklySchedule
from .services import (
    cancel_patient_appointment,
    create_confirmed_appointment,
    get_available_slots,
    get_patient_cancellation_state,
)


def next_weekday(weekday, from_date=None):
    start = from_date or timezone.localdate()
    days_ahead = (weekday - start.weekday()) % 7
    return start + timedelta(days=days_ahead)


class AppointmentTestDataMixin:
    def setUp(self):
        super().setUp()
        self.department, _ = Department.objects.get_or_create(
            name='General Consultation',
            defaults={
                'description': 'General medical consultations and routine care.',
                'is_active': True,
            },
        )
        tomorrow = timezone.localdate() + timedelta(days=1)
        self.appointment_day = next_weekday(tomorrow.weekday())
        WeeklySchedule.objects.create(
            weekday=self.appointment_day.weekday(),
            opening_time=time(9),
            closing_time=time(12),
        )

    def make_appointment(self, **overrides):
        values = {
            'first_name': 'Alex',
            'last_name': 'Example',
            'date_of_birth': date(1990, 1, 1),
            'gender': Appointment.Gender.PREFER_NOT_TO_SAY,
            'email': 'alex@example.com',
            'phone_number': '+15550101234',
            'street_address': '123 Example Street',
            'city': 'Sample City',
            'state_province': 'CA',
            'postal_code': '',
            'country': 'United States',
            'department': self.department,
            'requested_procedure': 'Routine consultation',
            'appointment_date': self.appointment_day,
            'start_time': time(9),
            'end_time': time(10),
            'comments': '',
        }
        values.update(overrides)
        return Appointment(**values)


class AvailabilityServiceTests(AppointmentTestDataMixin, TestCase):
    def test_returns_one_hour_slots_within_opening_hours(self):
        slots = get_available_slots(self.department, self.appointment_day)

        self.assertEqual(
            slots,
            [
                {'start_time': time(9), 'end_time': time(10)},
                {'start_time': time(10), 'end_time': time(11)},
                {'start_time': time(11), 'end_time': time(12)},
            ],
        )

    def test_unavailable_date_has_no_slots(self):
        UnavailableDate.objects.create(date=self.appointment_day, reason='Holiday')

        self.assertEqual(get_available_slots(self.department, self.appointment_day), [])

    def test_confirmed_appointments_are_excluded_but_cancelled_are_not(self):
        appointment = self.make_appointment()
        appointment.full_clean()
        appointment.save()

        slots = get_available_slots(self.department, self.appointment_day)
        self.assertNotIn({'start_time': time(9), 'end_time': time(10)}, slots)

        appointment.status = Appointment.Status.CANCELLED
        appointment.save()
        slots = get_available_slots(self.department, self.appointment_day)
        self.assertIn({'start_time': time(9), 'end_time': time(10)}, slots)

    def test_slots_that_do_not_fit_before_closing_are_not_returned(self):
        schedule = WeeklySchedule.objects.get(weekday=self.appointment_day.weekday())
        schedule.closing_time = time(9, 45)
        schedule.save()

        self.assertEqual(get_available_slots(self.department, self.appointment_day), [])


class AppointmentValidationTests(AppointmentTestDataMixin, TestCase):
    def test_postal_code_is_optional(self):
        values = {
            'first_name': 'Alex',
            'last_name': 'Example',
            'date_of_birth': date(1990, 1, 1),
            'gender': Appointment.Gender.PREFER_NOT_TO_SAY,
            'email': 'alex@example.com',
            'phone_number': '+15550101234',
            'street_address': '123 Example Street',
            'city': 'Sample City',
            'state_province': 'CA',
            'country': 'United States',
            'department': self.department,
            'requested_procedure': 'Routine consultation',
            'appointment_date': self.appointment_day,
            'start_time': time(9),
            'end_time': time(10),
        }
        appointment = Appointment(**values)
        appointment.full_clean()
        appointment.save()

        self.assertEqual(appointment.postal_code, '')

    def test_appointment_must_be_exactly_one_hour(self):
        appointment = self.make_appointment(end_time=time(10, 30))

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_appointment_must_be_inside_opening_hours(self):
        appointment = self.make_appointment(start_time=time(8), end_time=time(9))

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_appointment_must_start_on_a_schedule_slot(self):
        appointment = self.make_appointment(start_time=time(9, 30), end_time=time(10, 30))

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_new_appointment_cannot_use_inactive_department(self):
        self.department.is_active = False
        self.department.save()

        with self.assertRaises(ValidationError):
            self.make_appointment().full_clean()

    def test_appointment_on_unavailable_date_is_rejected(self):
        UnavailableDate.objects.create(date=self.appointment_day)
        appointment = self.make_appointment()

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_overlap_with_confirmed_appointment_is_rejected(self):
        existing = self.make_appointment()
        existing.full_clean()
        existing.save()
        overlapping = self.make_appointment(start_time=time(9, 30), end_time=time(10, 30))

        with self.assertRaises(ValidationError):
            overlapping.full_clean()

    def test_cancelled_appointment_does_not_block_rebooking(self):
        cancelled = self.make_appointment(status=Appointment.Status.CANCELLED)
        cancelled.full_clean()
        cancelled.save()
        replacement = self.make_appointment()

        replacement.full_clean()

    def test_booking_window_accepts_today_and_thirtieth_day(self):
        today = timezone.localdate()
        last_day = today + timedelta(days=30)
        for day in (today, last_day):
            WeeklySchedule.objects.update_or_create(
                weekday=day.weekday(),
                defaults={'opening_time': time(9), 'closing_time': time(12)},
            )
            appointment = self.make_appointment(appointment_date=day)
            local_morning = timezone.make_aware(datetime.combine(day, time(8)))
            with patch('appointments.models.timezone.localtime', return_value=local_morning):
                appointment.full_clean()

    def test_date_outside_booking_window_is_rejected(self):
        appointment = self.make_appointment(
            appointment_date=timezone.localdate() + timedelta(days=31),
        )

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_invalid_date_is_rejected(self):
        appointment = self.make_appointment(appointment_date=timezone.localdate() - timedelta(days=1))

        with self.assertRaises(ValidationError):
            appointment.full_clean()

    def test_booking_service_rejects_close_together_duplicate_requests(self):
        create_confirmed_appointment(self.make_appointment())

        with self.assertRaises(ValidationError):
            create_confirmed_appointment(self.make_appointment())


class AppointmentApiTests(AppointmentTestDataMixin, APITestCase):
    def test_timezone_is_central_africa_time_and_api_keeps_schedule_wall_clock(self):
        response = self.client.get(
            '/api/availability/',
            {'department': self.department.id, 'date': self.appointment_day.isoformat()},
        )

        self.assertEqual(settings.TIME_ZONE, 'Africa/Harare')
        self.assertTrue(settings.USE_TZ)
        self.assertEqual(
            response.data['available_dates'][0]['slots'][0],
            {'start_time': '09:00', 'end_time': '10:00'},
        )

    def test_health_endpoint_is_preserved(self):
        response = self.client.get('/api/health/')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.data['status'], 'ok')

    def test_only_active_departments_are_listed(self):
        inactive = Department.objects.create(name='Closed department', is_active=False)

        response = self.client.get('/api/departments/')

        self.assertEqual(response.status_code, 200)
        department_ids = [item['id'] for item in response.data]
        self.assertIn(self.department.id, department_ids)
        self.assertNotIn(inactive.id, department_ids)

    def test_availability_returns_generated_slots(self):
        response = self.client.get(
            '/api/availability/',
            {'department': self.department.id, 'date': self.appointment_day.isoformat()},
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            response.data['available_dates'][0]['slots'][0],
            {'start_time': '09:00', 'end_time': '10:00'},
        )

    def test_empty_schedule_returns_no_dates_or_slots(self):
        WeeklySchedule.objects.all().delete()

        response = self.client.get(
            '/api/availability/',
            {'department': self.department.id},
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.data['available_dates'], [])

    def test_unavailable_and_booked_slots_are_not_returned(self):
        booked = self.make_appointment()
        booked.full_clean()
        booked.save()
        response = self.client.get(
            '/api/availability/',
            {'department': self.department.id, 'date': self.appointment_day.isoformat()},
        )
        returned_slots = response.data['available_dates'][0]['slots']

        self.assertNotIn({'start_time': '09:00', 'end_time': '10:00'}, returned_slots)

        UnavailableDate.objects.create(date=self.appointment_day)
        response = self.client.get(
            '/api/availability/',
            {'department': self.department.id, 'date': self.appointment_day.isoformat()},
        )
        self.assertEqual(response.data['available_dates'][0]['slots'], [])

    def test_booking_window_boundaries(self):
        today = timezone.localdate()
        last_day = today + timedelta(days=30)
        WeeklySchedule.objects.update_or_create(
            weekday=today.weekday(),
            defaults={'opening_time': time(9), 'closing_time': time(10), 'is_active': True},
        )
        WeeklySchedule.objects.update_or_create(
            weekday=last_day.weekday(),
            defaults={'opening_time': time(9), 'closing_time': time(10), 'is_active': True},
        )

        for day in (today, last_day):
            response = self.client.get(
                '/api/availability/',
                {'department': self.department.id, 'date': day.isoformat()},
            )
            self.assertEqual(response.status_code, 200)
            self.assertEqual(response.data['available_dates'][0]['date'], day.isoformat())

    def test_invalid_dates_return_helpful_validation_errors(self):
        for invalid_date in [
            'not-a-date',
            (timezone.localdate() - timedelta(days=1)).isoformat(),
            (timezone.localdate() + timedelta(days=31)).isoformat(),
        ]:
            response = self.client.get(
                '/api/availability/',
                {'department': self.department.id, 'date': invalid_date},
            )
            self.assertEqual(response.status_code, 400)
            self.assertIn('date', response.data)

    def test_department_is_required_and_must_be_active(self):
        response = self.client.get('/api/availability/')
        self.assertEqual(response.status_code, 400)
        self.assertIn('department', response.data)

        self.department.is_active = False
        self.department.save()
        response = self.client.get('/api/availability/', {'department': self.department.id})
        self.assertEqual(response.status_code, 400)

    def test_appointment_endpoint_requires_authentication(self):
        response = self.client.post('/api/appointments/', {}, format='json')
        self.assertEqual(response.status_code, 403)

        history_response = self.client.get('/api/appointments/')
        self.assertEqual(history_response.status_code, 403)

    def test_availability_is_read_only(self):
        response = self.client.post('/api/availability/', {}, format='json')
        self.assertEqual(response.status_code, 405)


class PatientAuthenticationApiTests(APITestCase):
    def setUp(self):
        self.client = APIClient(enforce_csrf_checks=True)
        csrf_response = self.client.get('/api/auth/csrf/')
        self.csrf_token = csrf_response.data['csrf_token']
        self.valid_registration = {
            'full_name': 'Alex Example',
            'email': 'alex@example.com',
            'password': 'River!Stone728',
            'password_confirmation': 'River!Stone728',
        }

    def post_with_csrf(self, path, data):
        return self.client.post(
            path,
            data,
            format='json',
            HTTP_X_CSRFTOKEN=self.csrf_token,
        )

    def test_registration_creates_user_with_hashed_password_and_safe_response(self):
        response = self.post_with_csrf('/api/auth/register/', self.valid_registration)

        self.assertEqual(response.status_code, 201)
        self.assertNotIn('password', response.data)
        self.assertEqual(response.data['user']['email'], 'alex@example.com')
        self.assertTrue(response.data['csrf_token'])
        self.assertEqual(self.client.get('/api/auth/me/').status_code, 200)
        user = get_user_model().objects.get(email='alex@example.com')
        self.assertEqual(user.get_full_name(), 'Alex Example')
        self.assertNotEqual(user.password, self.valid_registration['password'])
        self.assertTrue(user.check_password(self.valid_registration['password']))

    def test_duplicate_email_is_rejected_case_insensitively(self):
        registration = self.post_with_csrf('/api/auth/register/', self.valid_registration)
        self.csrf_token = registration.data['csrf_token']
        duplicate = {**self.valid_registration, 'email': 'ALEX@EXAMPLE.COM'}

        response = self.post_with_csrf('/api/auth/register/', duplicate)

        self.assertEqual(response.status_code, 400)
        self.assertIn('email', response.data)
        self.assertEqual(get_user_model().objects.filter(email__iexact='alex@example.com').count(), 1)

    def test_registration_supports_valid_email_longer_than_username_limit(self):
        long_email = f"a@{'.'.join(['subdomainabcdefghij'] * 8)}.com"
        registration = {**self.valid_registration, 'email': long_email}

        response = self.post_with_csrf('/api/auth/register/', registration)

        self.assertEqual(response.status_code, 201)
        self.assertEqual(response.data['user']['email'], long_email)
        user = get_user_model().objects.get(email=long_email)
        self.assertLessEqual(len(user.username), 150)

    def test_password_confirmation_must_match(self):
        registration = {
            **self.valid_registration,
            'password_confirmation': 'Different!Password19',
        }

        response = self.post_with_csrf('/api/auth/register/', registration)

        self.assertEqual(response.status_code, 400)
        self.assertIn('password_confirmation', response.data)

    def test_django_password_validators_reject_weak_passwords(self):
        registration = {
            **self.valid_registration,
            'password': '12345678',
            'password_confirmation': '12345678',
        }

        response = self.post_with_csrf('/api/auth/register/', registration)

        self.assertEqual(response.status_code, 400)
        self.assertIn('password', response.data)
        self.assertFalse(get_user_model().objects.filter(email='alex@example.com').exists())

    def test_registration_requires_csrf_token(self):
        response = self.client.post(
            '/api/auth/register/',
            self.valid_registration,
            format='json',
        )

        self.assertEqual(response.status_code, 403)
        self.assertFalse(get_user_model().objects.filter(email='alex@example.com').exists())

    def test_login_and_logout_manage_a_django_session(self):
        registration = self.post_with_csrf('/api/auth/register/', self.valid_registration)
        self.assertEqual(registration.status_code, 201)

        credentials = {
            'email': 'ALEX@example.com',
            'password': self.valid_registration['password'],
        }
        csrf_denied_login = self.client.post('/api/auth/login/', credentials, format='json')
        self.assertEqual(csrf_denied_login.status_code, 403)
        self.csrf_token = registration.data['csrf_token']

        response = self.post_with_csrf('/api/auth/login/', credentials)

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.data['user']['email'], 'alex@example.com')
        self.assertNotIn('password', response.data)
        self.csrf_token = response.data['csrf_token']
        self.assertEqual(self.client.get('/api/auth/me/').status_code, 200)

        csrf_denied_logout = self.client.post('/api/auth/logout/', {}, format='json')
        self.assertEqual(csrf_denied_logout.status_code, 403)

        logout_response = self.post_with_csrf('/api/auth/logout/', {})

        self.assertEqual(logout_response.status_code, 200)
        self.assertEqual(self.client.get('/api/auth/me/').status_code, 403)

    def test_login_rejects_invalid_credentials(self):
        registration = self.post_with_csrf('/api/auth/register/', self.valid_registration)
        self.csrf_token = registration.data['csrf_token']

        response = self.post_with_csrf('/api/auth/login/', {
            'email': 'alex@example.com',
            'password': 'incorrect password',
        })

        self.assertEqual(response.status_code, 400)
        self.assertNotIn('user', response.data)

    def test_authenticated_user_endpoint_rejects_anonymous_requests(self):
        response = self.client.get('/api/auth/me/')

        self.assertEqual(response.status_code, 403)


class PatientBookingApiTests(AppointmentTestDataMixin, APITestCase):
    def setUp(self):
        super().setUp()
        self.client = APIClient(enforce_csrf_checks=True)
        user_model = get_user_model()
        self.patient = user_model.objects.create_user(
            username='booking-patient',
            email='booking-patient@example.com',
            first_name='Alex',
            last_name='Example',
        )
        self.other_patient = user_model.objects.create_user(
            username='other-patient',
            email='other-patient@example.com',
        )
        self.csrf_token = self.client.get('/api/auth/csrf/').data['csrf_token']
        self.client.force_login(self.patient)
        self.booking_payload = {
            'first_name': 'Alex',
            'last_name': 'Example',
            'date_of_birth': '1990-01-01',
            'gender': Appointment.Gender.PREFER_NOT_TO_SAY,
            'email': 'alex@example.com',
            'phone_number': '+15550101234',
            'street_address': '123 Example Street',
            'address_line_2': 'Suite 4',
            'city': 'Sample City',
            'state_province': 'CA',
            'country': 'United States',
            'department': self.department.id,
            'requested_procedure': 'Routine consultation',
            'appointment_date': self.appointment_day.isoformat(),
            'start_time': '09:00',
            'comments': '',
        }

    def book(self, payload=None, include_csrf=True):
        headers = {'HTTP_X_CSRFTOKEN': self.csrf_token} if include_csrf else {}
        return self.client.post(
            '/api/appointments/',
            payload or self.booking_payload,
            format='json',
            **headers,
        )

    def test_authenticated_patient_can_book_available_slot_and_omits_postal_code(self):
        response = self.book()

        self.assertEqual(response.status_code, 201, response.data)
        self.assertEqual(response.data['status'], Appointment.Status.CONFIRMED)
        self.assertEqual(response.data['start_time'], '09:00')
        self.assertEqual(response.data['end_time'], '10:00')
        self.assertNotIn('patient', response.data)
        appointment = Appointment.objects.get(booking_reference=response.data['booking_reference'])
        self.assertEqual(appointment.patient, self.patient)
        self.assertEqual(appointment.postal_code, '')
        self.assertEqual(appointment.address_line_2, 'Suite 4')

    def test_client_cannot_assign_booking_to_another_patient(self):
        payload = {**self.booking_payload, 'patient': self.other_patient.pk}

        response = self.book(payload)

        self.assertEqual(response.status_code, 201, response.data)
        appointment = Appointment.objects.get(booking_reference=response.data['booking_reference'])
        self.assertEqual(appointment.patient, self.patient)

    def test_booking_requires_csrf_even_for_signed_in_patient(self):
        response = self.book(include_csrf=False)

        self.assertEqual(response.status_code, 403)
        self.assertEqual(Appointment.objects.count(), 0)

    def test_staff_account_cannot_use_patient_booking_history_endpoint(self):
        staff_user = get_user_model().objects.create_user(
            username='clinic-staff',
            email='staff@example.com',
            is_staff=True,
        )
        self.client.force_login(staff_user)

        response = self.client.get('/api/appointments/')

        self.assertEqual(response.status_code, 403)

    def test_invalid_fields_and_inactive_departments_are_rejected(self):
        invalid_phone = {**self.booking_payload, 'phone_number': 'letters'}
        response = self.book(invalid_phone)
        self.assertEqual(response.status_code, 400)
        self.assertIn('phone_number', response.data)

        invalid_date = {**self.booking_payload, 'appointment_date': 'not-a-date'}
        response = self.book(invalid_date)
        self.assertEqual(response.status_code, 400)
        self.assertIn('appointment_date', response.data)

        self.department.is_active = False
        self.department.save()
        response = self.book()
        self.assertEqual(response.status_code, 400)
        self.assertIn('department', response.data)
        self.assertEqual(Appointment.objects.count(), 0)

    def test_unavailable_date_is_rejected(self):
        UnavailableDate.objects.create(date=self.appointment_day, reason='Clinic closed')

        response = self.book()

        self.assertEqual(response.status_code, 400)
        self.assertIn('appointment_date', response.data)
        self.assertEqual(Appointment.objects.count(), 0)

    def test_duplicate_booking_for_same_time_is_rejected(self):
        first_response = self.book()
        second_response = self.book()

        self.assertEqual(first_response.status_code, 201, first_response.data)
        self.assertEqual(second_response.status_code, 409)
        self.assertIn('start_time', second_response.data)
        self.assertEqual(Appointment.objects.count(), 1)

    def test_booking_references_are_unique(self):
        first = self.book()
        second_payload = {**self.booking_payload, 'start_time': '10:00'}
        second = self.book(second_payload)

        self.assertEqual(first.status_code, 201, first.data)
        self.assertEqual(second.status_code, 201, second.data)
        self.assertNotEqual(first.data['booking_reference'], second.data['booking_reference'])

    def test_booking_history_only_returns_current_patients_bookings(self):
        own_booking = self.book()
        other_booking = self.make_appointment(
            patient=self.other_patient,
            start_time=time(10),
            end_time=time(11),
        )
        create_confirmed_appointment(other_booking)

        response = self.client.get('/api/appointments/')

        self.assertEqual(response.status_code, 200)
        returned_references = [item['booking_reference'] for item in response.data]
        self.assertEqual(returned_references, [own_booking.data['booking_reference']])

    def test_email_failure_does_not_change_successful_database_booking(self):
        with self.assertLogs('appointments.views', level='ERROR'):
            with patch(
                'appointments.views.send_booking_notifications',
                side_effect=RuntimeError('SMTP is unavailable'),
            ):
                with self.captureOnCommitCallbacks(execute=True):
                    response = self.book()

        self.assertEqual(response.status_code, 201, response.data)
        self.assertTrue(
            Appointment.objects.filter(
                booking_reference=response.data['booking_reference'],
                patient=self.patient,
            ).exists()
        )


class BookingNotificationTests(AppointmentTestDataMixin, TestCase):
    @override_settings(
        MAILERS={
            'default': {
                'BACKEND': 'django.core.mail.backends.smtp.EmailBackend',
                'HOST': 'smtp.example.invalid',
                'PORT': 587,
                'USER': '',
                'PASSWORD': '',
                'USE_TLS': True,
            },
        },
        DEFAULT_FROM_EMAIL='clinic@example.invalid',
        CLINIC_STAFF_EMAIL='staff@example.invalid',
    )
    def test_configured_mailer_attempts_patient_and_staff_messages_independently(self):
        from .notifications import send_booking_notifications

        appointment = create_confirmed_appointment(self.make_appointment())
        with self.assertLogs('appointments.notifications', level='ERROR'):
            with patch(
                'appointments.notifications.send_mail',
                side_effect=[RuntimeError('SMTP is unavailable'), 1],
            ) as send_message:
                send_booking_notifications(appointment)

        self.assertEqual(send_message.call_count, 2)
        self.assertEqual(send_message.call_args_list[0].args[3], ['alex@example.com'])
        self.assertEqual(send_message.call_args_list[1].args[3], ['staff@example.invalid'])

    def test_unconfigured_mailer_does_not_attempt_delivery(self):
        from .notifications import send_booking_notifications

        appointment = create_confirmed_appointment(self.make_appointment())
        with patch('appointments.notifications.send_mail') as send_message:
            send_booking_notifications(appointment)

        send_message.assert_not_called()


class PatientCancellationApiTests(AppointmentTestDataMixin, APITestCase):
    def setUp(self):
        super().setUp()
        self.client = APIClient(enforce_csrf_checks=True)
        user_model = get_user_model()
        self.patient = user_model.objects.create_user(
            username='cancellation-patient',
            email='cancellation-patient@example.com',
        )
        self.other_patient = user_model.objects.create_user(
            username='cancellation-other',
            email='cancellation-other@example.com',
        )
        self.appointment = self.make_appointment(patient=self.patient)
        self.appointment.full_clean()
        self.appointment.save()
        self.csrf_token = self.client.get('/api/auth/csrf/').data['csrf_token']
        self.client.force_login(self.patient)

    def appointment_start(self):
        return timezone.make_aware(
            datetime.combine(self.appointment.appointment_date, self.appointment.start_time),
            timezone.get_current_timezone(),
        )

    def cancel(self, client=None, reference=None, include_csrf=True):
        request_client = client or self.client
        headers = {'HTTP_X_CSRFTOKEN': self.csrf_token} if include_csrf else {}
        return request_client.post(
            f'/api/appointments/{reference or self.appointment.booking_reference}/cancel/',
            {},
            format='json',
            **headers,
        )

    def test_patient_can_cancel_owned_appointment_more_than_12_hours_ahead(self):
        now = self.appointment_start() - timedelta(hours=12, minutes=1)
        with patch('appointments.services.timezone.now', return_value=now):
            response = self.cancel()

        self.assertEqual(response.status_code, 200, response.data)
        self.assertEqual(response.data['status'], Appointment.Status.CANCELLED)
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CANCELLED)

    def test_exactly_12_hours_before_start_is_allowed(self):
        now = self.appointment_start() - timedelta(hours=12)
        with patch('appointments.services.timezone.now', return_value=now):
            response = self.cancel()

        self.assertEqual(response.status_code, 200, response.data)

    def test_cancellation_inside_12_hour_window_is_rejected(self):
        now = self.appointment_start() - timedelta(hours=11, minutes=59)
        with patch('appointments.services.timezone.now', return_value=now):
            response = self.cancel()

        self.assertEqual(response.status_code, 409)
        self.assertIn('12 hours', response.data['detail'])
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CONFIRMED)

    def test_past_appointments_cannot_be_cancelled(self):
        now = self.appointment_start() + timedelta(minutes=1)
        with patch('appointments.services.timezone.now', return_value=now):
            response = self.cancel()

        self.assertEqual(response.status_code, 409)
        self.assertIn('Past appointments', response.data['detail'])
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CONFIRMED)

    def test_patient_cannot_cancel_another_patients_appointment(self):
        self.appointment.patient = self.other_patient
        self.appointment.save(update_fields=['patient'])

        response = self.cancel()

        self.assertEqual(response.status_code, 404)
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CONFIRMED)

    def test_unauthenticated_cancellation_is_rejected(self):
        anonymous_client = APIClient(enforce_csrf_checks=True)
        csrf = anonymous_client.get('/api/auth/csrf/').data['csrf_token']
        response = anonymous_client.post(
            f'/api/appointments/{self.appointment.booking_reference}/cancel/',
            {},
            format='json',
            HTTP_X_CSRFTOKEN=csrf,
        )

        self.assertEqual(response.status_code, 403)
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CONFIRMED)

    def test_repeated_cancellation_is_rejected_without_changing_status(self):
        now = self.appointment_start() - timedelta(hours=13)
        with patch('appointments.services.timezone.now', return_value=now):
            first_response = self.cancel()
            second_response = self.cancel()

        self.assertEqual(first_response.status_code, 200)
        self.assertEqual(second_response.status_code, 409)
        self.assertIn('already cancelled', second_response.data['detail'])
        self.appointment.refresh_from_db()
        self.assertEqual(self.appointment.status, Appointment.Status.CANCELLED)

    def test_cancelled_slot_returns_to_availability(self):
        before = get_available_slots(self.department, self.appointment_day)
        self.assertNotIn(
            {'start_time': time(9), 'end_time': time(10)},
            before,
        )
        now = self.appointment_start() - timedelta(hours=13)
        with patch('appointments.services.timezone.now', return_value=now):
            response = self.cancel()
        self.assertEqual(response.status_code, 200)

        after = get_available_slots(self.department, self.appointment_day)
        self.assertIn({'start_time': time(9), 'end_time': time(10)}, after)

    def test_history_marks_only_eligible_confirmed_bookings_cancellable(self):
        start = self.appointment_start()
        with patch('appointments.services.timezone.now', return_value=start - timedelta(hours=13)):
            eligible = self.client.get('/api/appointments/').data[0]
        self.assertTrue(eligible['can_cancel'])

        with patch('appointments.services.timezone.now', return_value=start - timedelta(hours=11)):
            too_late = self.client.get('/api/appointments/').data[0]
        self.assertFalse(too_late['can_cancel'])


class CancellationNotificationTests(AppointmentTestDataMixin, TestCase):
    @override_settings(
        MAILERS={
            'default': {
                'BACKEND': 'django.core.mail.backends.smtp.EmailBackend',
                'HOST': 'smtp.example.invalid',
                'PORT': 587,
                'USER': '',
                'PASSWORD': '',
                'USE_TLS': True,
            },
        },
        DEFAULT_FROM_EMAIL='clinic@example.invalid',
        CLINIC_STAFF_EMAIL='staff@example.invalid',
    )
    def test_configured_cancellation_notifications_attempt_patient_and_staff_messages(self):
        from .notifications import send_cancellation_notifications

        appointment = self.make_appointment()
        appointment.status = Appointment.Status.CANCELLED
        appointment.save()
        with patch(
            'appointments.notifications.send_mail',
            side_effect=[RuntimeError('SMTP unavailable'), 1],
        ) as send_message:
            with self.assertLogs('appointments.notifications', level='ERROR'):
                send_cancellation_notifications(appointment)

        self.assertEqual(send_message.call_count, 2)
        self.assertEqual(send_message.call_args_list[0].args[3], ['alex@example.com'])
        self.assertEqual(send_message.call_args_list[1].args[3], ['staff@example.invalid'])


class ConcurrentCancellationTests(AppointmentTestDataMixin, TransactionTestCase):
    def test_simultaneous_cancellation_requests_change_status_only_once(self):
        patient = get_user_model().objects.create_user(
            username='concurrent-cancellation-patient',
            email='concurrent-cancellation@example.com',
        )
        appointment = self.make_appointment(patient=patient)
        appointment.full_clean()
        appointment.save()
        appointment_start = timezone.make_aware(
            datetime.combine(appointment.appointment_date, appointment.start_time),
            timezone.get_current_timezone(),
        )
        barrier = Barrier(2)

        def attempt_cancel():
            close_old_connections()
            try:
                current_patient = get_user_model().objects.get(pk=patient.pk)
                barrier.wait(timeout=5)
                _, result = cancel_patient_appointment(
                    appointment.booking_reference,
                    current_patient,
                )
                return result
            finally:
                close_old_connections()

        with patch(
            'appointments.services.timezone.now',
            return_value=appointment_start - timedelta(hours=13),
        ):
            with ThreadPoolExecutor(max_workers=2) as executor:
                results = list(executor.map(lambda _: attempt_cancel(), range(2)))

        self.assertCountEqual(results, ['cancelled', 'not_confirmed'])
        appointment.refresh_from_db()
        self.assertEqual(appointment.status, Appointment.Status.CANCELLED)


class ConcurrentBookingTests(AppointmentTestDataMixin, TransactionTestCase):
    def test_simultaneous_requests_cannot_confirm_overlapping_appointments(self):
        barrier = Barrier(2)

        def attempt_booking():
            close_old_connections()
            try:
                appointment = self.make_appointment()
                barrier.wait(timeout=5)
                create_confirmed_appointment(appointment)
                return 'booked'
            except ValidationError:
                return 'rejected'
            finally:
                close_old_connections()

        with ThreadPoolExecutor(max_workers=2) as executor:
            results = list(executor.map(lambda _: attempt_booking(), range(2)))

        self.assertCountEqual(results, ['booked', 'rejected'])
        self.assertEqual(
            Appointment.objects.filter(
                appointment_date=self.appointment_day,
                status=Appointment.Status.CONFIRMED,
            ).count(),
            1,
        )
