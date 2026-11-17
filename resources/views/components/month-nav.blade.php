@props(['start', 'previous', 'next'])

<div {{ $attributes->merge(['class' => 'grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2']) }}>
    <a href="{{ route('roster.index', ['month' => $previous->month, 'year' => $previous->year]) }}" class="inline-flex h-9 justify-self-start items-center rounded-lg border border-line px-3 text-sm font-semibold hover:bg-surface-2">Previous</a>
    <div class="text-center">
        <div class="text-xl font-semibold tracking-tight sm:text-2xl">{{ $start->format('F Y') }}</div>
        <a href="{{ route('roster.index') }}" class="text-xs font-medium text-faint hover:text-ink">Today</a>
    </div>
    <a href="{{ route('roster.index', ['month' => $next->month, 'year' => $next->year]) }}" class="inline-flex h-9 justify-self-end items-center rounded-lg border border-line px-3 text-sm font-semibold hover:bg-surface-2">Next</a>
</div>
