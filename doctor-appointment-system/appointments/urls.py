from django.urls import path

from .views import (
    availability,
    cancel_patient_appointment_view,
    csrf_token,
    current_user,
    department_list,
    health_check,
    login_view,
    logout_view,
    patient_appointments,
    register,
)

urlpatterns = [
    path('health/', health_check, name='health-check'),
    path('auth/csrf/', csrf_token, name='auth-csrf'),
    path('auth/register/', register, name='auth-register'),
    path('auth/login/', login_view, name='auth-login'),
    path('auth/me/', current_user, name='auth-current-user'),
    path('auth/logout/', logout_view, name='auth-logout'),
    path('appointments/', patient_appointments, name='patient-appointments'),
    path(
        'appointments/<str:booking_reference>/cancel/',
        cancel_patient_appointment_view,
        name='patient-appointment-cancel',
    ),
    path('departments/', department_list, name='department-list'),
    path('availability/', availability, name='availability'),
]
