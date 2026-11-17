@props(['status'])

@php
    $styles = [
        'SHIFT' => 'bg-success-container text-on-success-container',
        'OFF' => 'bg-surface-highest text-on-surface-variant',
        'AL' => 'bg-warning-container text-on-warning-container',
        'DP' => 'bg-secondary-container text-on-secondary-container',
        'PENDING' => 'bg-secondary-container text-on-secondary-container',
        'APPROVED' => 'bg-success-container text-on-success-container',
        'REJECTED' => 'bg-error-container text-on-error-container',
        'IT Manager' => 'role-manager',
        'IT Staff' => 'role-staff',
        'IT Trainee' => 'role-trainee',
        'MANAGER' => 'role-manager',
        'STAFF' => 'role-staff',
        'SCHEDULE_CHANGE' => 'bg-tertiary-container text-on-tertiary-container',
        'SL' => 'bg-tertiary-container text-on-tertiary-container',
        'PH' => 'bg-error-container text-on-error-container',
    ];
    $labels = [
        'SCHEDULE_CHANGE' => 'Schedule',
        'MANAGER' => 'IT Manager',
        'STAFF' => 'IT Staff',
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'REJECTED' => 'Rejected',
        'AL' => 'Annual leave',
        'DP' => 'Day pending',
        'SL' => 'Sick leave',
        'SHIFT' => 'ON',
        'PH' => 'PH',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'm3-chip m3-chip-selected border-0 '.($styles[$status] ?? 'bg-surface-highest text-on-surface')]) }}>
    {{ $labels[$status] ?? $status }}
</span>
