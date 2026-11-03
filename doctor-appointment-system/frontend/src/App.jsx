import { useEffect, useState } from 'react'
import './App.css'

const API_BASE_URL = '/api'

function getCatDateString(date) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Africa/Harare',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date)
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]))

  return `${values.year}-${values.month}-${values.day}`
}

async function fetchApi(url, options = {}) {
  const requestOptions = options instanceof AbortSignal ? { signal: options } : options
  const response = await fetch(`${API_BASE_URL}${url}`, {
    credentials: 'same-origin',
    ...requestOptions,
  })
  const data = await response.json().catch(() => null)

  if (!response.ok) {
    const message = data?.detail
      || (response.status === 403
        ? 'The security check failed. Refresh the page and try again.'
        : `Request failed (${response.status})`)
    const error = new Error(message)
    error.status = response.status
    error.data = data
    throw error
  }

  return data
}

function getErrorMessages(data) {
  if (Array.isArray(data)) return data.flatMap(getErrorMessages)
  if (typeof data === 'string') return [data]
  if (!data || typeof data !== 'object') return ['Please check your information and try again.']

  return Object.entries(data).flatMap(([field, messages]) => {
    const prefix = field === 'non_field_errors'
      ? ''
      : `${field.replaceAll('_', ' ')}: `
    return getErrorMessages(messages).map((message) => `${prefix}${message}`)
  })
}

function formatCatTime(time) {
  const [hours, minutes] = time.split(':').map(Number)
  const period = hours >= 12 ? 'PM' : 'AM'
  const displayHour = hours % 12 || 12

  return `${displayHour}:${String(minutes).padStart(2, '0')} ${period} CAT`
}

function formatCatDate(date) {
  const [year, month, day] = date.split('-').map(Number)
  return new Intl.DateTimeFormat('en', {
    timeZone: 'Africa/Harare',
    dateStyle: 'long',
  }).format(new Date(Date.UTC(year, month - 1, day, 12)))
}

function createEmptyFormData() {
  return {
    firstName: '',
    lastName: '',
    dateOfBirth: '',
    gender: '',
    phone: '',
    email: '',
    addressLine1: '',
    addressLine2: '',
    city: '',
    region: '',
    postalCode: '',
    country: '',
    department: '',
    procedure: '',
    appointmentDate: '',
    appointmentTime: '',
    comments: '',
  }
}

// A React component is a reusable piece of the page. App is the main form.
function App() {
  // State keeps the current input values and lets React refresh the page as they change.
  const [formData, setFormData] = useState(createEmptyFormData)
  const [today] = useState(() => getCatDateString(new Date()))
  const [appointmentDateParts, setAppointmentDateParts] = useState({
    day: '',
    month: '',
    year: '',
  })
  const [appointmentDateError, setAppointmentDateError] = useState('')
  const [departments, setDepartments] = useState([])
  const [departmentsLoading, setDepartmentsLoading] = useState(true)
  const [departmentsError, setDepartmentsError] = useState('')
  const [departmentRetry, setDepartmentRetry] = useState(0)
  const [availability, setAvailability] = useState(null)
  const [availabilityLoading, setAvailabilityLoading] = useState(false)
  const [availabilityError, setAvailabilityError] = useState('')
  const [availabilityRetry, setAvailabilityRetry] = useState(0)
  const [authUser, setAuthUser] = useState(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [authMode, setAuthMode] = useState('register')
  const [authBusy, setAuthBusy] = useState(false)
  const [authError, setAuthError] = useState('')
  const [authMessage, setAuthMessage] = useState('')
  const [csrfToken, setCsrfToken] = useState('')
  const [bookingBusy, setBookingBusy] = useState(false)
  const [bookingError, setBookingError] = useState('')
  const [bookingConfirmation, setBookingConfirmation] = useState(null)
  const [appointments, setAppointments] = useState([])
  const [appointmentsLoading, setAppointmentsLoading] = useState(false)
  const [appointmentsError, setAppointmentsError] = useState('')
  const [appointmentsRetry, setAppointmentsRetry] = useState(0)
  const [cancellingReference, setCancellingReference] = useState('')
  const [cancellationMessage, setCancellationMessage] = useState('')
  const [cancellationError, setCancellationError] = useState('')
  const [authForm, setAuthForm] = useState({
    fullName: '',
    email: '',
    password: '',
    passwordConfirmation: '',
  })

  useEffect(() => {
    const controller = new AbortController()

    async function loadAuthentication() {
      try {
        const csrf = await fetchApi('/auth/csrf/', { signal: controller.signal })
        setCsrfToken(csrf.csrf_token)
        const result = await fetchApi('/auth/me/', { signal: controller.signal })
        setAuthUser(result.user)
      } catch (error) {
        if (error.name !== 'AbortError' && error.status !== 401 && error.status !== 403) {
          setAuthError('Could not connect to account services. Check that the backend is running.')
        }
      } finally {
        if (!controller.signal.aborted) setAuthLoading(false)
      }
    }

    loadAuthentication()
    return () => controller.abort()
  }, [])

  useEffect(() => {
    if (!authUser) {
      return undefined
    }

    const controller = new AbortController()
    async function loadAppointments() {
      setAppointmentsLoading(true)
      setAppointmentsError('')
      try {
        const result = await fetchApi('/appointments/', { signal: controller.signal })
        setAppointments(result)
      } catch (error) {
        if (error.name !== 'AbortError') {
          setAppointmentsError('Could not load your appointments. Check your connection and retry.')
        }
      } finally {
        if (!controller.signal.aborted) setAppointmentsLoading(false)
      }
    }

    loadAppointments()
    return () => controller.abort()
  }, [authUser, appointmentsRetry])

  // The development server forwards these same-origin requests to Django.
  useEffect(() => {
    const controller = new AbortController()

    async function loadDepartments() {
      setDepartmentsLoading(true)
      setDepartmentsError('')
      try {
        const result = await fetchApi('/departments/', controller.signal)
        setDepartments(result)
      } catch (error) {
        if (error.name !== 'AbortError') {
          setDepartmentsError('Could not load departments. Check that the backend is running, then retry.')
        }
      } finally {
        if (!controller.signal.aborted) setDepartmentsLoading(false)
      }
    }

    loadDepartments()
    return () => controller.abort()
  }, [departmentRetry])

  // Ask Django for availability whenever the department or requested date changes.
  useEffect(() => {
    if (!formData.department) {
      return undefined
    }

    const controller = new AbortController()
    const query = new URLSearchParams({ department: formData.department })
    if (formData.appointmentDate) query.set('date', formData.appointmentDate)

    async function loadAvailability() {
      setAvailabilityLoading(true)
      setAvailabilityError('')
      try {
        const result = await fetchApi(`/availability/?${query}`, controller.signal)
        setAvailability(result)
        const dateResult = result.available_dates.find(
          (item) => item.date === formData.appointmentDate,
        )
        const returnedSlots = dateResult?.slots ?? []
        setFormData((currentData) => {
          if (
            currentData.appointmentTime
            && !returnedSlots.some((slot) => slot.start_time === currentData.appointmentTime)
          ) {
            return { ...currentData, appointmentTime: '' }
          }
          return currentData
        })
      } catch (error) {
        if (error.name !== 'AbortError') {
          setAvailabilityError('Could not load appointment times. Check your connection and retry.')
          setFormData((currentData) => ({ ...currentData, appointmentTime: '' }))
        }
      } finally {
        if (!controller.signal.aborted) setAvailabilityLoading(false)
      }
    }

    loadAvailability()
    return () => controller.abort()
  }, [
    formData.appointmentDate,
    formData.department,
    availabilityRetry,
  ])

  // Each controlled input writes its latest value back into React state.
  function handleChange(event) {
    const { name, value } = event.target

    if (name === 'department') {
      setAvailability(null)
      setAvailabilityError('')
      setAvailabilityLoading(false)
      setAppointmentDateParts({ day: '', month: '', year: '' })
      setAppointmentDateError('')
    }

    setFormData((currentData) => {
      const nextData = { ...currentData, [name]: value }
      if (name === 'department') {
        nextData.appointmentDate = ''
        nextData.appointmentTime = ''
      }
      return nextData
    })
  }

  function handleAppointmentDatePartChange(event) {
    const { name, value } = event.target
    const digits = value.replace(/\D/g, '').slice(0, name === 'year' ? 4 : 2)
    const nextParts = { ...appointmentDateParts, [name]: digits }
    setAppointmentDateParts(nextParts)
    setAppointmentDateError('')
    setFormData((currentData) => ({
      ...currentData,
      appointmentDate: '',
      appointmentTime: '',
    }))

    if (!nextParts.day || !nextParts.month || nextParts.year.length !== 4) return

    const isoDate = `${nextParts.year}-${nextParts.month.padStart(2, '0')}-${nextParts.day.padStart(2, '0')}`
    const parsedDate = new Date(`${isoDate}T00:00:00Z`)
    const isRealDate = !Number.isNaN(parsedDate.getTime())
      && parsedDate.toISOString().slice(0, 10) === isoDate

    if (!isRealDate) {
      setAppointmentDateError('Enter a valid day, month, and year.')
      return
    }

    const windowStart = availability?.window.start ?? today
    const windowEnd = availability?.window.end
    if (isoDate < windowStart || (windowEnd && isoDate > windowEnd)) {
      setAppointmentDateError(
        `Choose a date from ${windowStart} through ${windowEnd ?? '30 days from today'}.`,
      )
      return
    }

    setFormData((currentData) => ({ ...currentData, appointmentDate: isoDate }))
  }

  function handleSubmit(event) {
    event.preventDefault()
    if (!authUser) {
      setBookingError('Sign in to your patient account before booking.')
      return
    }
    setBookingBusy(true)
    setBookingError('')
    setBookingConfirmation(null)

    const accountNameParts = authUser.full_name.trim().split(/\s+/)
    const payload = {
      first_name: formData.firstName || accountNameParts[0] || '',
      last_name: formData.lastName || accountNameParts.slice(1).join(' '),
      date_of_birth: formData.dateOfBirth,
      gender: formData.gender,
      email: formData.email || authUser.email,
      phone_number: formData.phone,
      street_address: formData.addressLine1,
      address_line_2: formData.addressLine2,
      city: formData.city,
      state_province: formData.region,
      postal_code: formData.postalCode,
      country: formData.country,
      department: formData.department,
      requested_procedure: formData.procedure,
      appointment_date: formData.appointmentDate,
      start_time: formData.appointmentTime,
      comments: formData.comments,
    }

    fetchApi('/appointments/', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRFToken': csrfToken,
      },
      body: JSON.stringify(payload),
    }).then((result) => {
      setBookingConfirmation(result)
      setFormData((currentData) => ({ ...currentData, appointmentTime: '' }))
      setAvailabilityRetry((retry) => retry + 1)
      setAppointmentsRetry((retry) => retry + 1)
    }).catch((error) => {
      if (error.status === 409) {
        setBookingError('That time was just booked. Choose another available time and submit again.')
        setAvailabilityRetry((retry) => retry + 1)
      } else {
        setBookingError(getErrorMessages(error.data ?? error.message).join(' '))
      }
    }).finally(() => setBookingBusy(false))
  }

  function handleAuthInputChange(event) {
    const { name, value } = event.target
    setAuthForm((currentForm) => ({ ...currentForm, [name]: value }))
    setAuthError('')
    setAuthMessage('')
  }

  function switchAuthMode(mode) {
    setAuthMode(mode)
    setAuthError('')
    setAuthMessage('')
    setAuthForm((currentForm) => ({
      ...currentForm,
      password: '',
      passwordConfirmation: '',
    }))
  }

  async function handleAuthSubmit(event) {
    event.preventDefault()
    setAuthBusy(true)
    setAuthError('')
    setAuthMessage('')

    const registering = authMode === 'register'
    const payload = registering
      ? {
          full_name: authForm.fullName,
          email: authForm.email,
          password: authForm.password,
          password_confirmation: authForm.passwordConfirmation,
        }
      : { email: authForm.email, password: authForm.password }

    try {
      const result = await fetchApi(`/auth/${registering ? 'register' : 'login'}/`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRFToken': csrfToken,
        },
        body: JSON.stringify(payload),
      })

      setAuthForm((currentForm) => ({
        ...currentForm,
        password: '',
        passwordConfirmation: '',
      }))
      if (registering) {
        setAuthUser(result.user)
        setCsrfToken(result.csrf_token)
        setAuthMessage('Your account was created successfully. You are now signed in.')
      } else {
        setAuthUser(result.user)
        setCsrfToken(result.csrf_token)
        setAuthMessage(`You logged in successfully. Welcome, ${result.user.full_name}.`)
      }
    } catch (error) {
      setAuthError(getErrorMessages(error.data ?? error.message).join(' '))
    } finally {
      setAuthBusy(false)
    }
  }

  async function handleLogout() {
    setAuthBusy(true)
    setAuthError('')
    setAuthMessage('')
    try {
      await fetchApi('/auth/logout/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRFToken': csrfToken,
        },
        body: JSON.stringify({}),
      })
      setAuthUser(null)
      setFormData(createEmptyFormData())
      setAppointmentDateParts({ day: '', month: '', year: '' })
      setAppointmentDateError('')
      setAvailability(null)
      setBookingConfirmation(null)
      setBookingError('')
      setAppointments([])
      setAppointmentsError('')
      setAppointmentsLoading(false)
      setAuthMode('login')
      setAuthMessage('You have been signed out.')
    } catch (error) {
      setAuthError(getErrorMessages(error.data ?? error.message).join(' '))
    } finally {
      setAuthBusy(false)
    }
  }

  async function handleCancelAppointment(appointment) {
    const confirmed = window.confirm(
      `Cancel appointment ${appointment.booking_reference} on ${formatCatDate(appointment.appointment_date)} at ${formatCatTime(appointment.start_time)}?`,
    )
    if (!confirmed) return

    setCancellingReference(appointment.booking_reference)
    setCancellationMessage('')
    setCancellationError('')
    try {
      await fetchApi(`/appointments/${encodeURIComponent(appointment.booking_reference)}/cancel/`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRFToken': csrfToken,
        },
        body: JSON.stringify({}),
      })
      setCancellationMessage(
        `Appointment ${appointment.booking_reference} was cancelled successfully.`,
      )
      setAppointmentsRetry((retry) => retry + 1)
      setAvailabilityRetry((retry) => retry + 1)
    } catch (error) {
      setCancellationError(getErrorMessages(error.data ?? error.message).join(' '))
      setAppointmentsRetry((retry) => retry + 1)
      setAvailabilityRetry((retry) => retry + 1)
    } finally {
      setCancellingReference('')
    }
  }

  const selectedDateAvailability = availability?.available_dates.find(
    (item) => item.date === formData.appointmentDate,
  )
  const availableSlots = selectedDateAvailability?.slots ?? []
  const accountNameParts = authUser?.full_name.trim().split(/\s+/) ?? []
  const upcomingAppointments = appointments.filter(
    (appointment) => appointment.status === 'confirmed' && appointment.appointment_date >= today,
  )
  const pastAppointments = appointments.filter(
    (appointment) => !upcomingAppointments.includes(appointment),
  )

  return (
    <div className="app-shell">
      <header className="site-header">
        <a className="brand" href="#main-content" aria-label="Pharmaid Clinic home">
          <span className="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none">
              <path d="M13 6h6v7h7v6h-7v7h-6v-7H6v-6h7V6Z" fill="currentColor" />
            </svg>
          </span>
          <span className="brand-name">Pharmaid<span> Clinic</span></span>
        </a>
        {authUser ? (
          <div className="header-account">
            <span className="header-note">
              <span className="header-dot" aria-hidden="true" />
              Signed in: {authUser.full_name}
            </span>
            <button
              className="header-action"
              disabled={authBusy}
              onClick={handleLogout}
              type="button"
            >
              Sign out
            </button>
          </div>
        ) : (
          <span className="header-note">
            <span className="header-dot" aria-hidden="true" />
            Patient services
          </span>
        )}
      </header>

      <main className="main-content" id="main-content">
        <section className="page-intro" aria-labelledby="page-title">
          <p className="eyebrow">YOUR HEALTH, OUR PRIORITY</p>
          <h1 id="page-title">{authUser ? 'Request an appointment' : 'Welcome to Pharmaid Clinic'}</h1>
          <p className="intro-copy">
            {authUser
              ? 'Check live clinic availability and book a preferred appointment time.'
              : 'Please log in or create a patient account to continue to appointment booking.'}
          </p>
        </section>

        <section className="auth-card" aria-labelledby="account-heading">
          {authLoading ? (
            <p className="field-status" role="status">Checking your account…</p>
          ) : authUser ? (
            <>
              <div className="auth-confirmation" role="status">
                <span className="account-check" aria-hidden="true">✓</span>
                <div>
                  <h2 id="account-heading">You’re signed in</h2>
                  <p>{authUser.full_name} · {authUser.email}</p>
                  {authMessage && <p className="auth-feedback success" role="status">{authMessage}</p>}
                  {!authMessage && (
                    <p className="field-status">Your patient account is ready to book an appointment.</p>
                  )}
                </div>
              </div>
              {authError && <p className="auth-feedback error" role="alert">{authError}</p>}
            </>
          ) : (
            <>
              <div className="auth-card-heading">
                <div>
                  <p className="eyebrow">PATIENT ACCOUNT</p>
                  <h2 id="account-heading">{authMode === 'register' ? 'Create your account' : 'Welcome back'}</h2>
                </div>
                <div className="auth-switch" aria-label="Account action">
                  <button
                    aria-pressed={authMode === 'register'}
                    className={authMode === 'register' ? 'auth-tab active' : 'auth-tab'}
                    onClick={() => switchAuthMode('register')}
                    type="button"
                  >
                    Sign up
                  </button>
                  <button
                    aria-pressed={authMode === 'login'}
                    className={authMode === 'login' ? 'auth-tab active' : 'auth-tab'}
                    onClick={() => switchAuthMode('login')}
                    type="button"
                  >
                    Log in
                  </button>
                </div>
              </div>

              {authError && <p className="auth-feedback error" role="alert">{authError}</p>}
              {authMessage && <p className="auth-feedback success" role="status">{authMessage}</p>}

              {authMode === 'register' ? (
                <form className="auth-form" onSubmit={handleAuthSubmit}>
                  <div className="field">
                    <label htmlFor="authFullName">Full name <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="name"
                      id="authFullName"
                      name="fullName"
                      onChange={handleAuthInputChange}
                      required
                      value={authForm.fullName}
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="authRegisterEmail">Email address <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="email"
                      id="authRegisterEmail"
                      name="email"
                      onChange={handleAuthInputChange}
                      required
                      type="email"
                      value={authForm.email}
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="authRegisterPassword">Password <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="new-password"
                      id="authRegisterPassword"
                      minLength={8}
                      name="password"
                      onChange={handleAuthInputChange}
                      required
                      type="password"
                      value={authForm.password}
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="authPasswordConfirmation">Confirm password <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="new-password"
                      id="authPasswordConfirmation"
                      minLength={8}
                      name="passwordConfirmation"
                      onChange={handleAuthInputChange}
                      required
                      type="password"
                      value={authForm.passwordConfirmation}
                    />
                  </div>
                  <button className="auth-submit" disabled={authBusy || !csrfToken} type="submit">
                    {authBusy ? 'Creating account…' : 'Create account'}
                  </button>
                  <p className="auth-help">
                    Passwords are checked and securely hashed by Django. Never reuse a password from another account.
                  </p>
                </form>
              ) : (
                <form className="auth-form" onSubmit={handleAuthSubmit}>
                  <div className="field">
                    <label htmlFor="authLoginEmail">Email address <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="email"
                      id="authLoginEmail"
                      name="email"
                      onChange={handleAuthInputChange}
                      required
                      type="email"
                      value={authForm.email}
                    />
                  </div>
                  <div className="field">
                    <label htmlFor="authLoginPassword">Password <span aria-hidden="true">*</span></label>
                    <input
                      autoComplete="current-password"
                      id="authLoginPassword"
                      name="password"
                      onChange={handleAuthInputChange}
                      required
                      type="password"
                      value={authForm.password}
                    />
                  </div>
                  <button className="auth-submit" disabled={authBusy || !csrfToken} type="submit">
                    {authBusy ? 'Logging in…' : 'Log in'}
                  </button>
                </form>
              )}
            </>
          )}
        </section>

        {authUser && (
          <>
        {bookingConfirmation && (
          <section className="booking-confirmation" aria-labelledby="booking-confirmation-heading" role="status">
            <span className="account-check" aria-hidden="true">✓</span>
            <div className="booking-confirmation-copy">
              <p className="eyebrow">BOOKING SAVED</p>
              <h2 id="booking-confirmation-heading">Your appointment is confirmed</h2>
              <p>
                Confirmation reference: <strong>{bookingConfirmation.booking_reference}</strong>
              </p>
              <dl className="confirmation-details">
                <div><dt>Patient</dt><dd>{bookingConfirmation.first_name} {bookingConfirmation.last_name}</dd></div>
                <div><dt>Department</dt><dd>{bookingConfirmation.department}</dd></div>
                <div><dt>Requested</dt><dd>{bookingConfirmation.requested_procedure}</dd></div>
                <div><dt>Date</dt><dd>{formatCatDate(bookingConfirmation.appointment_date)} (CAT)</dd></div>
                <div><dt>Time</dt><dd>{formatCatTime(bookingConfirmation.start_time)} – {formatCatTime(bookingConfirmation.end_time)}</dd></div>
              </dl>
            </div>
          </section>
        )}

        {authUser && (
          <section className="appointments-card" aria-labelledby="appointments-heading">
            <div className="appointments-card-heading">
              <div>
                <p className="eyebrow">YOUR ACCOUNT</p>
                <h2 id="appointments-heading">Your appointments</h2>
              </div>
              <button
                className="text-button"
                disabled={appointmentsLoading}
                onClick={() => setAppointmentsRetry((retry) => retry + 1)}
                type="button"
              >
                {appointmentsLoading ? 'Refreshing…' : 'Refresh'}
              </button>
            </div>
            {cancellationMessage && (
              <p className="cancellation-feedback success" role="status">{cancellationMessage}</p>
            )}
            {cancellationError && (
              <p className="cancellation-feedback error" role="alert">{cancellationError}</p>
            )}
            {appointmentsLoading && <p className="field-status" role="status">Loading your appointments…</p>}
            {appointmentsError && (
              <div className="field-error" role="alert">
                <p>{appointmentsError}</p>
                <button
                  className="text-button"
                  onClick={() => setAppointmentsRetry((retry) => retry + 1)}
                  type="button"
                >
                  Retry
                </button>
              </div>
            )}
            {!appointmentsLoading && !appointmentsError && appointments.length === 0 && (
              <p className="field-status">You have no appointment bookings yet.</p>
            )}
            {!appointmentsLoading && !appointmentsError && upcomingAppointments.length > 0 && (
              <div className="appointment-list">
                <h3>Upcoming</h3>
                {upcomingAppointments.map((appointment) => (
                  <article className="appointment-history-item" key={appointment.booking_reference}>
                    <div className="history-main">
                      <strong>{appointment.department}</strong>
                      <span>{formatCatDate(appointment.appointment_date)} · {formatCatTime(appointment.start_time)}</span>
                    </div>
                    <div className="history-meta">
                      <span>{appointment.requested_procedure}</span>
                      <span>Ref: {appointment.booking_reference}</span>
                      {appointment.status === 'confirmed' && !appointment.can_cancel && (
                        <span className="cancellation-window-note">
                          Online cancellation is closed. Please contact clinic staff.
                        </span>
                      )}
                      {appointment.can_cancel && (
                        <button
                          className="cancel-appointment-button"
                          disabled={Boolean(cancellingReference)}
                          onClick={() => handleCancelAppointment(appointment)}
                          type="button"
                        >
                          {cancellingReference === appointment.booking_reference
                            ? 'Cancelling…'
                            : 'Cancel appointment'}
                        </button>
                      )}
                    </div>
                  </article>
                ))}
              </div>
            )}
            {!appointmentsLoading && !appointmentsError && pastAppointments.length > 0 && (
              <div className="appointment-list past-appointment-list">
                <h3>Past and cancelled</h3>
                {pastAppointments.map((appointment) => (
                  <article className="appointment-history-item" key={appointment.booking_reference}>
                    <div className="history-main">
                      <strong>{appointment.department}</strong>
                      <span>{formatCatDate(appointment.appointment_date)} · {formatCatTime(appointment.start_time)}</span>
                    </div>
                    <div className="history-meta">
                      <span>{appointment.requested_procedure}</span>
                      <span>{appointment.status} · Ref: {appointment.booking_reference}</span>
                    </div>
                  </article>
                ))}
              </div>
            )}
          </section>
        )}

        <div className="form-card">
          <div className="form-card-heading">
            <div>
              <h2>Appointment details</h2>
              <p>Fields marked <span aria-hidden="true">*</span> are required.</p>
            </div>
            <span className="step-indicator">PATIENT FORM</span>
          </div>

          <div className="demo-notice" role="note">
            <span className="notice-icon" aria-hidden="true">i</span>
            <p>
              <strong>Secure booking.</strong> When you submit while signed in,
              the clinic checks the slot again and saves your appointment before
              showing a confirmation. Please do not include sensitive medical
              details in optional comments.
            </p>
          </div>
          {bookingError && <p className="auth-feedback error" role="alert">{bookingError}</p>}

          {/* JSX looks like HTML, but uses JavaScript expressions and names such as className. */}
          <form onSubmit={handleSubmit}>
            <section className="form-section" aria-labelledby="patient-heading">
              <div className="section-heading">
                <span className="section-number" aria-hidden="true">01</span>
                <div>
                  <h3 id="patient-heading">Patient information</h3>
                  <p>Tell us who the appointment is for.</p>
                </div>
              </div>

              <div className="field-grid two-columns">
                <div className="field">
                  <label htmlFor="firstName">First name <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="given-name"
                    id="firstName"
                    name="firstName"
                    onChange={handleChange}
                    required
                    value={formData.firstName || accountNameParts[0] || ''}
                  />
                </div>
                <div className="field">
                  <label htmlFor="lastName">Last name <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="family-name"
                    id="lastName"
                    name="lastName"
                    onChange={handleChange}
                    required
                    value={formData.lastName || accountNameParts.slice(1).join(' ')}
                  />
                </div>
                <div className="field">
                  <label htmlFor="dateOfBirth">Date of birth <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="bday"
                    id="dateOfBirth"
                    max={today}
                    name="dateOfBirth"
                    onChange={handleChange}
                    required
                    type="date"
                    value={formData.dateOfBirth}
                  />
                </div>
                <div className="field">
                  <label htmlFor="gender">Gender <span aria-hidden="true">*</span></label>
                  <select
                    id="gender"
                    name="gender"
                    onChange={handleChange}
                    required
                    value={formData.gender}
                  >
                    <option disabled value="">Select an option</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                    <option value="non-binary">Non-binary</option>
                    <option value="prefer_not_to_say">Prefer not to say</option>
                  </select>
                </div>
              </div>
            </section>

            <section className="form-section" aria-labelledby="contact-heading">
              <div className="section-heading">
                <span className="section-number" aria-hidden="true">02</span>
                <div>
                  <h3 id="contact-heading">Contact information</h3>
                  <p>How can we reach you about your request?</p>
                </div>
              </div>

              <div className="field-grid two-columns">
                <div className="field">
                  <label htmlFor="phone">Phone number <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="tel"
                    id="phone"
                    name="phone"
                    onChange={handleChange}
                    pattern="[+0-9]{7,15}"
                    placeholder="+15550101234"
                    required
                    title="Enter 7–15 characters using numbers and an optional + sign."
                    type="tel"
                    value={formData.phone}
                  />
                </div>
                <div className="field">
                  <label htmlFor="email">Email address <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="email"
                    id="email"
                    name="email"
                    onChange={handleChange}
                    placeholder="you@example.com"
                    required
                    type="email"
                    value={formData.email || authUser?.email || ''}
                  />
                </div>
              </div>
            </section>

            <section className="form-section" aria-labelledby="address-heading">
              <div className="section-heading">
                <span className="section-number" aria-hidden="true">03</span>
                <div>
                  <h3 id="address-heading">Address</h3>
                  <p>Add your current mailing address.</p>
                </div>
              </div>

              <div className="field-grid two-columns">
                <div className="field full-width">
                  <label htmlFor="addressLine1">Street address <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="address-line1"
                    id="addressLine1"
                    name="addressLine1"
                    onChange={handleChange}
                    required
                    value={formData.addressLine1}
                  />
                </div>
                <div className="field full-width">
                  <label htmlFor="addressLine2">Apartment, suite, etc. <span className="optional">(optional)</span></label>
                  <input
                    autoComplete="address-line2"
                    id="addressLine2"
                    name="addressLine2"
                    onChange={handleChange}
                    value={formData.addressLine2}
                  />
                </div>
                <div className="field">
                  <label htmlFor="city">City <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="address-level2"
                    id="city"
                    name="city"
                    onChange={handleChange}
                    required
                    value={formData.city}
                  />
                </div>
                <div className="field">
                  <label htmlFor="region">State / province <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="address-level1"
                    id="region"
                    name="region"
                    onChange={handleChange}
                    required
                    value={formData.region}
                  />
                </div>
                <div className="field">
                  <label htmlFor="postalCode">Postal / ZIP code <span className="optional">(optional)</span></label>
                  <input
                    autoComplete="postal-code"
                    id="postalCode"
                    name="postalCode"
                    onChange={handleChange}
                    value={formData.postalCode}
                  />
                </div>
                <div className="field">
                  <label htmlFor="country">Country <span aria-hidden="true">*</span></label>
                  <input
                    autoComplete="country-name"
                    id="country"
                    name="country"
                    onChange={handleChange}
                    required
                    value={formData.country}
                  />
                </div>
              </div>
            </section>

            <section className="form-section" aria-labelledby="visit-heading">
              <div className="section-heading">
                <span className="section-number" aria-hidden="true">04</span>
                <div>
                  <h3 id="visit-heading">Visit details</h3>
                  <p>Choose a department and check live clinic availability.</p>
                </div>
              </div>

              <div className="field-grid two-columns">
                <div className="field">
                  <label htmlFor="department">Department <span aria-hidden="true">*</span></label>
                  <select
                    id="department"
                    name="department"
                    onChange={handleChange}
                    disabled={departmentsLoading || Boolean(departmentsError)}
                    required
                    value={formData.department}
                  >
                    <option value="">
                      {departmentsLoading ? 'Loading departments…' : 'Select a department'}
                    </option>
                    {departments.map((department) => (
                      <option key={department.id} value={department.id}>{department.name}</option>
                    ))}
                  </select>
                  {departmentsLoading && (
                    <p className="field-status" role="status">Loading departments…</p>
                  )}
                  {departmentsError && (
                    <div className="field-error" role="alert">
                      <p>{departmentsError}</p>
                      <button
                        className="text-button"
                        onClick={() => setDepartmentRetry((retry) => retry + 1)}
                        type="button"
                      >
                        Retry
                      </button>
                    </div>
                  )}
                </div>
                <div className="field">
                  <label htmlFor="procedure">Procedure requested <span aria-hidden="true">*</span></label>
                  <input
                    id="procedure"
                    name="procedure"
                    onChange={handleChange}
                    placeholder="Briefly describe your request"
                    required
                    value={formData.procedure}
                  />
                </div>
                <fieldset className="field date-field">
                  <legend>Preferred date <span aria-hidden="true">*</span></legend>
                  <div className="date-parts">
                    <label className="date-part">
                      <span>Day</span>
                      <input
                        aria-label="Preferred date day"
                        autoComplete="off"
                        disabled={!formData.department || !availability?.window}
                        inputMode="numeric"
                        maxLength={2}
                        name="day"
                        onChange={handleAppointmentDatePartChange}
                        placeholder="DD"
                        required
                        value={appointmentDateParts.day}
                      />
                    </label>
                    <label className="date-part">
                      <span>Month</span>
                      <input
                        aria-label="Preferred date month"
                        autoComplete="off"
                        disabled={!formData.department || !availability?.window}
                        inputMode="numeric"
                        maxLength={2}
                        name="month"
                        onChange={handleAppointmentDatePartChange}
                        placeholder="MM"
                        required
                        value={appointmentDateParts.month}
                      />
                    </label>
                    <label className="date-part date-year">
                      <span>Year</span>
                      <input
                        aria-label="Preferred date year"
                        autoComplete="off"
                        disabled={!formData.department || !availability?.window}
                        inputMode="numeric"
                        maxLength={4}
                        name="year"
                        onChange={handleAppointmentDatePartChange}
                        placeholder="YYYY"
                        required
                        value={appointmentDateParts.year}
                      />
                    </label>
                  </div>
                  <p className={appointmentDateError ? 'field-error-text' : 'field-status'} role={appointmentDateError ? 'alert' : undefined}>
                    {appointmentDateError || (
                      availability?.window
                        ? `Enter a date from ${availability.window.start} through ${availability.window.end} (CAT).`
                        : 'Select a department to load the booking window.'
                    )}
                  </p>
                </fieldset>
                <div className="field">
                  <label htmlFor="appointmentTime">Available appointment time <span aria-hidden="true">*</span></label>
                  <select
                    id="appointmentTime"
                    name="appointmentTime"
                    onChange={handleChange}
                    disabled={
                      !formData.department
                      || !formData.appointmentDate
                      || availabilityLoading
                      || Boolean(availabilityError)
                      || availableSlots.length === 0
                    }
                    required
                    value={formData.appointmentTime}
                  >
                    <option value="">
                      {availabilityLoading ? 'Loading available times…' : 'Select an available time'}
                    </option>
                    {availableSlots.map((slot) => (
                      <option key={slot.start_time} value={slot.start_time}>
                        {formatCatTime(slot.start_time)} – {formatCatTime(slot.end_time)}
                      </option>
                    ))}
                  </select>
                  {availabilityLoading && (
                    <p className="field-status" role="status">Checking the clinic schedule…</p>
                  )}
                  {availabilityError && (
                    <div className="field-error" role="alert">
                      <p>{availabilityError}</p>
                      <button
                        className="text-button"
                        onClick={() => setAvailabilityRetry((retry) => retry + 1)}
                        type="button"
                      >
                        Retry
                      </button>
                    </div>
                  )}
                  {!availabilityLoading && !availabilityError && formData.department && !formData.appointmentDate && availability && (
                    <p className="field-status" role="status">
                      {availability.available_dates.length === 0
                        ? 'No appointment times are currently listed. The clinic schedule may not be configured yet.'
                        : 'Choose a date in the next 30 days to check its available times.'}
                    </p>
                  )}
                  {!availabilityLoading && !availabilityError && formData.appointmentDate && availableSlots.length === 0 && (
                    <p className="field-status" role="status">
                      No times are available on this date. Try another date.
                    </p>
                  )}
                  {!availabilityLoading && !availabilityError && formData.department && availability && (
                    <button
                      className="text-button"
                      onClick={() => setAvailabilityRetry((retry) => retry + 1)}
                      type="button"
                    >
                      Refresh availability
                    </button>
                  )}
                </div>
              </div>
              <p className="slot-disclaimer">
                Available times come from the clinic schedule and are shown in
                Central Africa Time (CAT). Checking a time does not reserve it.
              </p>
            </section>

            <section className="form-section comments-section" aria-labelledby="comments-heading">
              <div className="section-heading">
                <span className="section-number" aria-hidden="true">05</span>
                <div>
                  <h3 id="comments-heading">Additional comments</h3>
                  <p>Anything else you’d like us to know? <span className="optional">(optional)</span></p>
                </div>
              </div>
              <div className="field">
                <label htmlFor="comments">Comments <span className="optional">(optional)</span></label>
                <textarea
                  id="comments"
                  name="comments"
                  onChange={handleChange}
                  placeholder="Add a note (please do not include sensitive medical information)"
                  rows="4"
                  value={formData.comments}
                />
              </div>
            </section>

            <div className="form-actions">
              <p className="required-note"><span aria-hidden="true">*</span> Required fields</p>
              <button
                className="submit-button"
                disabled={!authUser || authLoading || bookingBusy || availabilityLoading || Boolean(availabilityError) || !csrfToken}
                type="submit"
              >
                {bookingBusy ? 'Saving appointment…' : authUser ? 'Confirm appointment' : 'Sign in to book'}
              </button>
              {!authUser && <p className="booking-disabled-note">Sign in to your patient account above to book a time.</p>}
            </div>
          </form>
        </div>

        <p className="page-footnote">
          Appointments are saved in the clinic database. Availability and appointment times use Central Africa Time (CAT).
        </p>
          </>
        )}
      </main>
    </div>
  )
}

export default App
