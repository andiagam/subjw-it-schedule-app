@props(['start', 'previous', 'next'])

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    <a href="{{ route('roster.index', ['month' => $previous->month, 'year' => $previous->year]) }}" class="grid size-9 place-items-center rounded-full border border-outline-variant bg-surface-lowest text-on-surface hover:bg-surface-high" aria-label="Previous month">
        <x-icon name="chevron_left" class="text-[20px]" />
    </a>
    <div class="min-w-36 px-2 text-center text-sm font-medium">{{ $start->format('F Y') }}</div>
    <a href="{{ route('roster.index', ['month' => $next->month, 'year' => $next->year]) }}" class="grid size-9 place-items-center rounded-full border border-outline-variant bg-surface-lowest text-on-surface hover:bg-surface-high" aria-label="Next month">
        <x-icon name="chevron_right" class="text-[20px]" />
    </a>
    <a href="{{ route('roster.index') }}" class="ml-1 inline-flex h-9 items-center rounded-full px-3 text-sm font-medium text-primary hover:bg-primary-container">Today</a>
</div>
