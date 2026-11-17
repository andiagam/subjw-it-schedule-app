@props(['status'])

@php
    $styles = [
        'SHIFT' => 'bg-emerald-100 text-emerald-800',
        'OFF' => 'bg-slate-200 text-slate-700',
        'AL' => 'bg-amber-100 text-amber-900',
        'DP' => 'bg-indigo-100 text-indigo-800',
        'PENDING' => 'bg-sky-100 text-sky-800',
        'APPROVED' => 'bg-emerald-100 text-emerald-800',
        'REJECTED' => 'bg-rose-100 text-rose-800',
        'MANAGER' => 'bg-slate-900 text-white',
        'STAFF' => 'bg-slate-100 text-slate-700',
        'SCHEDULE_CHANGE' => 'bg-violet-100 text-violet-800',
        'SL' => 'bg-pink-100 text-pink-800',
    ];
    $labels = [
        'SCHEDULE_CHANGE' => 'Schedule',
        'MANAGER' => 'Manager',
        'STAFF' => 'Staff',
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'REJECTED' => 'Rejected',
        'AL' => 'Annual leave',
        'DP' => 'Day pending',
        'SL' => 'Sick leave',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide '.($styles[$status] ?? 'bg-slate-100 text-slate-700')]) }}>
    {{ $labels[$status] ?? $status }}
</span>
