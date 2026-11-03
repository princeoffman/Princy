# Django backend

The backend uses Django, Django REST Framework, and the existing SQLite database.
It provides health, department, and read-only availability endpoints, plus
patient registration, session-based authentication, protected booking creation,
and patient-owned booking history.

## Run locally (Windows PowerShell)

From the project folder:

```powershell
cd .\backend
.\.venv\Scripts\python.exe manage.py runserver
```

The development server is available at <http://127.0.0.1:8000>. It is for local
development only.

Local development generates an in-memory secret key when `DJANGO_SECRET_KEY`
is not set. This key changes when Django restarts, which is fine for this
learning project. When `DJANGO_DEBUG` is set to `false`, Django requires
`DJANGO_SECRET_KEY`; set allowed host names with `DJANGO_ALLOWED_HOSTS`. These
settings alone are not a complete production deployment configuration.

## API

- `GET /api/health/` — returns `{"status":"ok","message":"Backend is running"}`.
- `GET /api/departments/` — lists active departments with their ID, name, and
  description.
- `GET /api/availability/?department=1` — returns dates and available
  one-hour slots in the inclusive 30-day booking window. Replace `1` with an
  active department ID.
- `GET /api/availability/?department=1&date=YYYY-MM-DD` — returns slots for one
  date. The date must be today through 30 days from today.

Availability is calculated using the weekly schedule and unavailable dates.
Confirmed appointments are removed from the results. Django uses
`Africa/Harare` (Central Africa Time) with timezone-aware datetimes enabled.
Admin schedule times and API slot strings are local clinic wall-clock times;
the API returns them as `HH:MM` without browser-side timezone conversion.
Invalid department IDs,
invalid dates, and dates outside the booking window return HTTP 400 with a
field-specific JSON error. A date with no available slots returns an empty
`slots` list.

## Patient accounts and authentication

- `GET /api/auth/csrf/` — prepares the CSRF cookie and returns the request token.
- `POST /api/auth/register/` — accepts `full_name`, `email`, `password`, and
  `password_confirmation`. Email addresses are normalized and duplicate
  accounts are rejected. Django's configured password validators are applied.
  Successful registration also starts a signed-in session and returns the
  safe user summary and refreshed CSRF token.
- `POST /api/auth/login/` — accepts `email` and `password`, then starts a Django
  session and returns a safe user summary plus a refreshed CSRF token.
- `GET /api/auth/me/` — requires a signed-in session and returns the user's
  ID, full name, and email. It returns HTTP 403 for an unauthenticated request
  because Django's session authentication does not send an HTTP auth challenge.
- `POST /api/auth/logout/` — ends the current Django session.

The password is written to the database only as Django's password hash; it is
never returned by the API. Registration, login, and logout require a valid
`X-CSRFToken` header.
The frontend gets this token from the CSRF endpoint and keeps it in React
memory; the session and CSRF cookies are HTTP-only and SameSite=Lax.

Vite proxies `/api` to Django at `http://127.0.0.1:8000` and preserves the
browser's host so Django can validate the same-origin CSRF request. This means
the local setup does not need CORS. A deployment should serve the frontend and
API on the same origin (for example, through a reverse proxy), use HTTPS, and
configure the production host and secret settings; do not broaden CSRF/CORS
trust just to work around a misconfigured proxy.

## Booking and history

- `POST /api/appointments/` — requires an authenticated patient and valid
  `X-CSRFToken`. Accepts the patient's contact/address details, active
  department ID, requested date, and requested `start_time` (`HH:MM` in CAT).
  Postal code and address line two are optional. The server calculates the
  60-minute end time and assigns the booking to the current session user; any
  patient ID supplied in the request is ignored. Before saving, the server
  rechecks the clinic hours, date window, closures, current time, and existing
  bookings. Success returns HTTP 201 only after the appointment has been saved,
  including its unique reference and confirmed status. A slot taken by another
  request returns HTTP 409.
- `GET /api/appointments/` — requires an authenticated patient and lists only
  appointments owned by that session user. The response includes upcoming and
  past bookings but excludes other patients' data. Each item includes
  `can_cancel`, calculated by the backend using the clinic's CAT clock.
- `POST /api/appointments/<booking_reference>/cancel/` — requires the owning
  signed-in patient and a valid `X-CSRFToken`. Patient cancellation is allowed
  only while the appointment is confirmed, has not started, and is at least
  12 hours away. The exact 12-hour boundary is allowed. A booking inside that
  window or in the past returns HTTP 409 with a message to contact clinic
  staff. Another patient's booking is reported as not found.

Cancellation changes a confirmed appointment to cancelled inside a database
transaction. SQLite's writer lock and a conditional status update ensure
simultaneous cancellation requests cannot cancel the same appointment twice.
Cancelled appointments no longer block the slot in availability results.

The appointment's patient relationship is nullable and uses `SET_NULL`, so
adding ownership preserves existing appointment rows. Existing records remain
unassigned until staff decide how to associate them; new API bookings always
use the authenticated account.

## Email notifications

Email is currently **not configured for actual delivery**: the default mail
backend remains Django's console backend, and no SMTP host, sender address, or
staff notification address is configured. Bookings still save normally, but
no email is sent in this state.

To enable SMTP, set these environment variables in the backend terminal before
starting Django. Use values supplied by your mail provider; never put secrets
in React, committed files, screenshots, or chat:

```powershell
$env:EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
$env:EMAIL_HOST = '<your SMTP host>'
$env:EMAIL_PORT = '587'
$env:EMAIL_HOST_USER = '<your SMTP username>'
$env:EMAIL_HOST_PASSWORD = '<your SMTP password or app password>'
$env:EMAIL_USE_TLS = 'true'
$env:EMAIL_USE_SSL = 'false'
$env:DEFAULT_FROM_EMAIL = '<clinic sender address>'
$env:CLINIC_STAFF_EMAIL = '<clinic staff notification address>'
```

Restart Django after setting these values. TLS and SSL should not both be
enabled. After a database commit, Django attempts a booking-reference email to
the patient and a booking notification to the configured staff address. Mail
errors are logged server-side and do not undo a saved appointment or change a
successful booking response. Test delivery only with mail accounts you
control; no live SMTP delivery has been verified for this project.
The same configuration is used for patient/staff cancellation notifications.
Email failures do not undo a saved cancellation. No live SMTP delivery has
been verified.

## Run both development servers (Windows PowerShell)

Use two terminals opened at the project folder.

**Terminal 1 — Django:**

```powershell
cd .\backend
.\.venv\Scripts\python.exe manage.py runserver 127.0.0.1:8000
```

**Terminal 2 — React/Vite:**

```powershell
cd .\frontend
npm run dev -- --host 127.0.0.1
```

## Django admin

After you create a local administrator, open <http://127.0.0.1:8000/admin/>.
Create an account yourself; this project does not include an administrator
password:

```powershell
cd .\backend
.\.venv\Scripts\python.exe manage.py createsuperuser
```

Staff can use the admin to maintain departments, weekly hours, unavailable
dates, and appointment records. Initial departments are inserted by a data
migration without replacing existing department descriptions or active status.

## Tests and migrations

```powershell
cd .\backend
.\.venv\Scripts\python.exe manage.py check
.\.venv\Scripts\python.exe manage.py test appointments
.\.venv\Scripts\python.exe manage.py migrate
```

The ownership and optional second address-line fields were added by migration
`0002_appointment_address_line_2_appointment_patient`. It adds nullable and
defaulted fields without deleting or resetting existing records.
