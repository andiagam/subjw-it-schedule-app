@props([
    'mode' => 'range',
    'startName' => 'start_date',
    'endName' => 'end_date',
    'start' => '',
    'end' => '',
    'clearable' => false,
    'required' => false,
    'named' => true,
    'startModel' => null,
    'endModel' => null,
    'datesName' => null,
    'dates' => '',
])

<div
    {{ $attributes->merge(['class' => 'date-picker']) }}
    data-date-picker
    data-mode="{{ $mode === 'single' ? 'single' : 'range' }}"
    data-today="{{ \Carbon\CarbonImmutable::now('Asia/Jakarta')->toDateString() }}"
    @if($clearable) data-clearable @endif
>
    <button type="button" class="date-picker-field" data-picker-open aria-haspopup="dialog">
        <span class="material-symbols-rounded date-picker-icon" aria-hidden="true">calendar_month</span>
        <span class="date-picker-value" data-picker-label>{{ $mode === 'single' ? 'Select date' : 'Select dates' }}</span>
        <span class="date-picker-count" data-picker-count hidden>1 day</span>
    </button>
    <input
        type="hidden"
        data-picker-start
        value="{{ $start }}"
        @if($named && $startName !== '') name="{{ $startName }}" @endif
        @if($startModel) x-model="{{ $startModel }}" @endif
        @if($required) required @endif
    >
    <input
        type="hidden"
        data-picker-end
        value="{{ $end }}"
        @if($named && $endName !== '') name="{{ $endName }}" @endif
        @if($endModel) x-model="{{ $endModel }}" @endif
    >
    @if($datesName)
        <input type="hidden" name="{{ $datesName }}" data-picker-dates value="{{ $dates }}">
    @endif
    <div class="date-picker-popover z-[70]" data-picker-popover role="dialog" aria-label="Choose dates" hidden>
        <div class="date-picker-modes" data-picker-modes hidden>
            <button type="button" class="date-picker-mode" data-picker-action="mode-range">Range</button>
            <button type="button" class="date-picker-mode" data-picker-action="mode-multiple">Multiple dates</button>
        </div>
        <div class="date-picker-months" data-picker-months></div>
        <div class="date-picker-actions">
            <button type="button" class="btn-text state-layer" data-picker-action="today">Today</button>
            <button type="button" class="btn-text state-layer" data-picker-action="clear" data-picker-clear>Clear</button>
            <button type="button" class="btn-filled state-layer" data-picker-action="apply">Apply</button>
        </div>
    </div>
</div>
