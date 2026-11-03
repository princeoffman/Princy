from django.contrib import admin

from .models import Appointment, Department, UnavailableDate, WeeklySchedule
from .services import create_confirmed_appointment


@admin.register(Department)
class DepartmentAdmin(admin.ModelAdmin):
    list_display = ['name', 'is_active']
    list_filter = ['is_active']
    search_fields = ['name', 'description']
    ordering = ['name']


@admin.register(WeeklySchedule)
class WeeklyScheduleAdmin(admin.ModelAdmin):
    list_display = ['get_weekday_display', 'opening_time', 'closing_time', 'is_active']
    list_filter = ['is_active', 'weekday']
    ordering = ['weekday']


@admin.register(UnavailableDate)
class UnavailableDateAdmin(admin.ModelAdmin):
    list_display = ['date', 'reason']
    list_filter = ['date']
    search_fields = ['reason']
    ordering = ['date']


@admin.register(Appointment)
class AppointmentAdmin(admin.ModelAdmin):
    list_display = [
        'booking_reference',
        'first_name',
        'last_name',
        'patient',
        'department',
        'appointment_date',
        'start_time',
        'status',
    ]
    list_filter = ['status', 'department', 'appointment_date']
    search_fields = [
        'booking_reference',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'patient__email',
    ]
    readonly_fields = ['booking_reference', 'created_at']
    date_hierarchy = 'appointment_date'
    ordering = ['appointment_date', 'start_time']

    def save_model(self, request, obj, form, change):
        if obj.status == Appointment.Status.CONFIRMED:
            create_confirmed_appointment(obj)
        else:
            super().save_model(request, obj, form, change)
