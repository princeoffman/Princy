import hashlib
from datetime import datetime
from datetime import timedelta

from django.contrib.auth import authenticate, get_user_model
from django.contrib.auth.password_validation import validate_password
from django.core.exceptions import ValidationError as DjangoValidationError
from django.db import IntegrityError, transaction
from django.utils import timezone
from rest_framework import serializers

from .models import APPOINTMENT_DURATION, BOOKING_WINDOW_DAYS, Appointment, Department
from .services import create_confirmed_appointment, get_patient_cancellation_state

User = get_user_model()


def _username_for_email(email):
    digest = hashlib.sha256(email.encode('utf-8')).hexdigest()
    return f'patient-{digest}'


class RegistrationSerializer(serializers.Serializer):
    full_name = serializers.CharField(max_length=150)
    email = serializers.EmailField(max_length=254)
    password = serializers.CharField(write_only=True, trim_whitespace=False)
    password_confirmation = serializers.CharField(write_only=True, trim_whitespace=False)

    def validate_email(self, value):
        normalized_email = value.strip().lower()
        if User.objects.filter(email__iexact=normalized_email).exists():
            raise serializers.ValidationError('An account with this email already exists.')
        return normalized_email

    def validate(self, attrs):
        full_name = ' '.join(attrs['full_name'].split())
        name_parts = full_name.split(' ')
        if len(name_parts) < 2:
            raise serializers.ValidationError({
                'full_name': 'Enter your first and last name.',
            })

        if attrs['password'] != attrs['password_confirmation']:
            raise serializers.ValidationError({
                'password_confirmation': 'The passwords do not match.',
            })

        attrs['full_name'] = full_name
        attrs['first_name'] = name_parts[0]
        attrs['last_name'] = ' '.join(name_parts[1:])
        user = User(
            username=_username_for_email(attrs['email']),
            email=attrs['email'],
            first_name=attrs['first_name'],
            last_name=attrs['last_name'],
        )
        try:
            validate_password(attrs['password'], user=user)
        except DjangoValidationError as error:
            raise serializers.ValidationError({'password': error.messages}) from error
        return attrs

    def create(self, validated_data):
        try:
            with transaction.atomic():
                return User.objects.create_user(
                    username=_username_for_email(validated_data['email']),
                    email=validated_data['email'],
                    password=validated_data['password'],
                    first_name=validated_data['first_name'],
                    last_name=validated_data['last_name'],
                )
        except IntegrityError as error:
            raise serializers.ValidationError({
                'email': 'An account with this email already exists.',
            }) from error


class LoginSerializer(serializers.Serializer):
    email = serializers.EmailField()
    password = serializers.CharField(write_only=True, trim_whitespace=False)

    def validate(self, attrs):
        email = attrs['email'].strip().lower()
        account = User.objects.filter(email__iexact=email).first()
        user = None
        if account:
            user = authenticate(
                request=self.context.get('request'),
                username=account.get_username(),
                password=attrs['password'],
            )
        if user is None:
            raise serializers.ValidationError('Email or password is incorrect.')
        attrs['user'] = user
        return attrs


class AuthenticatedUserSerializer(serializers.Serializer):
    id = serializers.IntegerField(read_only=True)
    full_name = serializers.SerializerMethodField()
    email = serializers.EmailField(read_only=True)

    def get_full_name(self, user):
        return user.get_full_name() or user.email


class AppointmentCreateSerializer(serializers.Serializer):
    first_name = serializers.CharField(max_length=100)
    last_name = serializers.CharField(max_length=100)
    date_of_birth = serializers.DateField()
    gender = serializers.ChoiceField(choices=Appointment.Gender.choices)
    email = serializers.EmailField()
    phone_number = serializers.RegexField(
        regex=r'^\+?[0-9]{7,15}$',
        max_length=30,
    )
    street_address = serializers.CharField(max_length=255)
    address_line_2 = serializers.CharField(max_length=255, required=False, allow_blank=True)
    city = serializers.CharField(max_length=100)
    state_province = serializers.CharField(max_length=100)
    postal_code = serializers.CharField(max_length=20, required=False, allow_blank=True)
    country = serializers.CharField(max_length=100)
    department = serializers.PrimaryKeyRelatedField(
        queryset=Department.objects.filter(is_active=True),
    )
    requested_procedure = serializers.CharField(max_length=255)
    appointment_date = serializers.DateField()
    start_time = serializers.TimeField()
    comments = serializers.CharField(required=False, allow_blank=True)

    def create(self, validated_data):
        validated_data['end_time'] = (
            datetime.combine(
                validated_data['appointment_date'],
                validated_data['start_time'],
            ) + APPOINTMENT_DURATION
        ).time()
        appointment = Appointment(
            **validated_data,
            patient=self.context['request'].user,
        )
        try:
            return create_confirmed_appointment(appointment)
        except DjangoValidationError as error:
            if hasattr(error, 'error_dict'):
                detail = {
                    field: [str(item.message) for item in messages]
                    for field, messages in error.error_dict.items()
                }
            else:
                detail = {'non_field_errors': error.messages}
            raise serializers.ValidationError(detail) from error


class AppointmentHistorySerializer(serializers.ModelSerializer):
    department = serializers.CharField(source='department.name', read_only=True)
    start_time = serializers.TimeField(format='%H:%M', read_only=True)
    end_time = serializers.TimeField(format='%H:%M', read_only=True)
    can_cancel = serializers.SerializerMethodField()

    def get_can_cancel(self, appointment):
        return (
            appointment.status == Appointment.Status.CONFIRMED
            and get_patient_cancellation_state(appointment) == 'allowed'
        )

    class Meta:
        model = Appointment
        fields = [
            'booking_reference',
            'id',
            'first_name',
            'last_name',
            'email',
            'department',
            'requested_procedure',
            'appointment_date',
            'start_time',
            'end_time',
            'status',
            'created_at',
            'can_cancel',
        ]


class DepartmentSerializer(serializers.ModelSerializer):
    class Meta:
        model = Department
        fields = ['id', 'name', 'description']


class AvailabilityQuerySerializer(serializers.Serializer):
    department = serializers.PrimaryKeyRelatedField(
        queryset=Department.objects.filter(is_active=True),
    )
    date = serializers.DateField(required=False)

    def validate_date(self, value):
        today = timezone.localdate()
        last_booking_date = today + timedelta(days=BOOKING_WINDOW_DAYS)
        if not today <= value <= last_booking_date:
            raise serializers.ValidationError(
                f'Choose a date from {today} through {last_booking_date}.'
            )
        return value


class TimeSlotSerializer(serializers.Serializer):
    start_time = serializers.TimeField(format='%H:%M')
    end_time = serializers.TimeField(format='%H:%M')


class AvailableDateSerializer(serializers.Serializer):
    date = serializers.DateField()
    slots = TimeSlotSerializer(many=True)
