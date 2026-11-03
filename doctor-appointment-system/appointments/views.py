import logging
from datetime import timedelta

from django.contrib.auth import login, logout
from django.db import transaction
from django.middleware.csrf import get_token, rotate_token
from django.views.decorators.csrf import csrf_protect, ensure_csrf_cookie
from django.utils import timezone
from rest_framework import serializers, status
from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response

from .models import BOOKING_WINDOW_DAYS, Appointment, Department
from .permissions import IsPatient
from .serializers import (
    AvailabilityQuerySerializer,
    AvailableDateSerializer,
    AuthenticatedUserSerializer,
    AppointmentCreateSerializer,
    AppointmentHistorySerializer,
    DepartmentSerializer,
    LoginSerializer,
    RegistrationSerializer,
)
from .notifications import send_booking_notifications, send_cancellation_notifications
from .services import cancel_patient_appointment, get_available_slots


logger = logging.getLogger(__name__)


def csrf_protected_api(view):
    # DRF marks function API views exempt; clear that marker after wrapping.
    protected_view = csrf_protect(view)
    if hasattr(protected_view, 'csrf_exempt'):
        del protected_view.csrf_exempt
    return protected_view


@api_view(['GET'])
def health_check(request):
    return Response({
        'status': 'ok',
        'message': 'Backend is running',
    })


@ensure_csrf_cookie
@api_view(['GET'])
def csrf_token(request):
    """Set the CSRF cookie and provide a request token to the same-origin frontend."""
    return Response({'csrf_token': get_token(request)})


@csrf_protected_api
@api_view(['POST'])
def register(request):
    serializer = RegistrationSerializer(data=request.data)
    serializer.is_valid(raise_exception=True)
    user = serializer.save()
    login(request, user)
    rotate_token(request)
    return Response(
        {
            'message': 'Your account was created and you are now signed in.',
            'user': AuthenticatedUserSerializer(user).data,
            'csrf_token': get_token(request),
        },
        status=status.HTTP_201_CREATED,
    )


@csrf_protected_api
@api_view(['POST'])
def login_view(request):
    serializer = LoginSerializer(data=request.data, context={'request': request})
    serializer.is_valid(raise_exception=True)
    user = serializer.validated_data['user']
    login(request, user)
    rotate_token(request)
    return Response({
        'user': AuthenticatedUserSerializer(user).data,
        'csrf_token': get_token(request),
    })


@api_view(['GET'])
@permission_classes([IsAuthenticated])
def current_user(request):
    return Response({'user': AuthenticatedUserSerializer(request.user).data})


@csrf_protected_api
@api_view(['POST'])
def logout_view(request):
    logout(request)
    return Response({'message': 'You have been signed out.'})


@csrf_protected_api
@api_view(['GET', 'POST'])
@permission_classes([IsPatient])
def patient_appointments(request):
    """Create bookings for this session user or list only their bookings."""
    if request.method == 'GET':
        appointments = Appointment.objects.filter(
            patient=request.user,
        ).select_related('department')
        return Response(AppointmentHistorySerializer(appointments, many=True).data)

    serializer = AppointmentCreateSerializer(
        data=request.data,
        context={'request': request},
    )
    if not serializer.is_valid():
        if 'start_time' in serializer.errors:
            return Response(serializer.errors, status=status.HTTP_409_CONFLICT)
        return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)

    try:
        appointment = serializer.save()
    except serializers.ValidationError as error:
        error_status = (
            status.HTTP_409_CONFLICT
            if 'start_time' in error.detail
            else status.HTTP_400_BAD_REQUEST
        )
        return Response(error.detail, status=error_status)

    transaction.on_commit(lambda: send_booking_notifications_safely(appointment))
    return Response(
        AppointmentHistorySerializer(appointment).data,
        status=status.HTTP_201_CREATED,
    )


@csrf_protected_api
@api_view(['POST'])
@permission_classes([IsPatient])
def cancel_patient_appointment_view(request, booking_reference):
    appointment, result = cancel_patient_appointment(booking_reference, request.user)
    if result == 'not_found':
        return Response(
            {'detail': 'Appointment not found.'},
            status=status.HTTP_404_NOT_FOUND,
        )
    if result == 'notice_window':
        return Response(
            {'detail': 'Appointments must be cancelled at least 12 hours in advance. Please contact clinic staff for help.'},
            status=status.HTTP_409_CONFLICT,
        )
    if result == 'past':
        return Response(
            {'detail': 'Past appointments cannot be cancelled online. Please contact clinic staff if you need help.'},
            status=status.HTTP_409_CONFLICT,
        )
    if result == 'not_confirmed':
        return Response(
            {'detail': 'This appointment is already cancelled or is no longer active.'},
            status=status.HTTP_409_CONFLICT,
        )

    transaction.on_commit(lambda: send_cancellation_notifications_safely(appointment))
    return Response(AppointmentHistorySerializer(appointment).data)


def send_booking_notifications_safely(appointment):
    try:
        send_booking_notifications(appointment)
    except Exception:
        logger.exception(
            'Email notification processing failed for saved booking %s.',
            appointment.booking_reference,
        )


def send_cancellation_notifications_safely(appointment):
    try:
        send_cancellation_notifications(appointment)
    except Exception:
        logger.exception(
            'Cancellation email processing failed for saved booking %s.',
            appointment.booking_reference,
        )


@api_view(['GET'])
def department_list(request):
    """List only departments that are currently accepting appointment requests."""
    departments = Department.objects.filter(is_active=True)
    return Response(DepartmentSerializer(departments, many=True).data)


@api_view(['GET'])
def availability(request):
    """Return server-calculated one-hour slots for an active department."""
    query = AvailabilityQuerySerializer(data=request.query_params)
    query.is_valid(raise_exception=True)
    department = query.validated_data['department']
    requested_date = query.validated_data.get('date')
    today = timezone.localdate()

    if requested_date is None:
        dates = [
            today + timedelta(days=offset)
            for offset in range(BOOKING_WINDOW_DAYS + 1)
        ]
    else:
        dates = [requested_date]

    available_dates = [
        {'date': day, 'slots': get_available_slots(department, day)}
        for day in dates
    ]
    available_dates = [
        day for day in available_dates
        if requested_date is not None or day['slots']
    ]

    return Response({
        'department': DepartmentSerializer(department).data,
        'window': {
            'start': today,
            'end': today + timedelta(days=BOOKING_WINDOW_DAYS),
        },
        'available_dates': AvailableDateSerializer(available_dates, many=True).data,
    })
