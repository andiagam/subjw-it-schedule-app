@extends('layouts.app')

@section('title', $start->format('F Y'))

@section('content')
    @php
        $isManager = $currentUser?->isManager() ?? false;
        $badges = [
            'SHIFT' => 'badge-shift',
            'OFF' => 'badge-off',
            'AL' => 'badge-al',
            'DP' => 'badge-dp',
            'SL' => 'badge-sl',
            'PH' => 'badge-ph',
        ];
        $labels = [
            'SHIFT' => 'ON',
            'OFF' => 'OFF',
            'AL' => 'AL',
            'DP' => 'DP',
            'SL' => 'SL',
            'PH' => 'PH',
        ];
        $taskLabels = [
            'event' => 'Event',
            'task' => 'Task',
            'holiday' => 'Public holiday',
        ];
        $chipFor = fn ($kind) => $kind === 'event'
            ? 'bg-primary-container text-on-primary-container'
            : ($kind === 'holiday' ? 'bg-error-container text-on-error-container' : 'bg-success-container text-on-success-container');
    @endphp

    <div x-data="rosterPage(@js([
        'taskOpen' => $errors->any() && old('form') === 'task',
        'taskDate' => old('date', ''),
        'taskDateLabel' => old('date', ''),
        'taskKind' => old('kind', 'task'),
        'taskTitle' => old('title', ''),
    ]))">
        @php
            $today = \Carbon\CarbonImmutable::now()->startOfDay();
            $todayInMonth = $today->betweenIncluded($start, $start->endOfMonth());
            $todayKey = $today->toDateString();
            $todayTasks = $tasks[$todayKey] ?? collect();
            $statusNames = [
                'SHIFT' => 'Working',
                'OFF' => 'Off',
                'AL' => 'Annual leave',
                'DP' => 'Day pending',
                'SL' => 'Sick leave',
                'PH' => 'Public holiday',
            ];
            $todayGroups = [];
            if ($todayInMonth) {
                foreach ($users as $person) {
                    $todayEntry = $entries->get($person->id.'|'.$todayKey);
                    $key = $todayEntry->status ?? 'EMPTY';
                    $todayGroups[$key][] = $person->name;
                }
            }
        @endphp

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <x-month-nav :start="$start" :previous="$previous" :next="$next" />
            @if($isManager)
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('roster.autofill') }}" onsubmit="return confirm('Fill empty days this month? Weekdays become ON, weekends become OFF. Public holidays and existing entries stay as they are.')">
                        @csrf
                        <input type="hidden" name="month" value="{{ $start->month }}">
                        <input type="hidden" name="year" value="{{ $start->year }}">
                        <button type="submit" class="btn-tonal state-layer h-9 px-4 text-sm">Auto-fill</button>
                    </form>
                    <a href="{{ route('roster.export', ['month' => $start->month, 'year' => $start->year]) }}" class="btn-outlined state-layer h-9 px-4 text-sm">Export</a>
                </div>
            @endif
        </div>
        <p class="mb-4 text-sm text-on-surface-variant">The IT manager edits any schedule directly. Everyone else asks through Requests. Leave left: <span class="font-medium text-on-surface">{{ $currentUser->al_balance }} annual</span> · <span class="font-medium text-on-surface">{{ $currentUser->dp_balance }} day pending</span>.</p>

        <section>
        <h2 class="mb-3 text-sm font-medium">Shift Roster</h2>
        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-on-surface-variant">
            @foreach(['SHIFT' => 'Working', 'OFF' => 'Off', 'AL' => 'Annual leave', 'DP' => 'Day pending', 'SL' => 'Sick leave', 'PH' => 'Public holiday'] as $status => $name)
                <span class="inline-flex items-center gap-1.5">
                    <span class="roster-badge {{ $badges[$status] }}">{{ $labels[$status] }}</span>
                    {{ $name }}
                </span>
            @endforeach
        </div>

        <div data-month-board data-roster-scroll class="m3-card max-h-[70vh] overflow-auto !p-0">
            <table class="min-w-max border-separate border-spacing-0 text-xs">
                <thead>
                    <tr>
                        <th class="sticky top-0 left-0 z-30 w-36 min-w-36 bg-surface-lowest px-3 py-2 text-left shadow-[4px_0_8px_-6px_rgba(15,23,42,0.28)] sm:w-48">Employee</th>
                        @foreach($days as $day)
                            @php
                                $dayTasks = $tasks[$day->toDateString()] ?? collect();
                                $holidayName = $dayTasks->where('kind', 'holiday')->pluck('title')->implode(', ');
                                $isRed = $day->isWeekend() || $holidayName !== '';
                            @endphp
                            <th @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif @if($day->isToday()) data-today="1" @endif @if($holidayName !== '') title="{{ $holidayName }}" @endif class="sticky top-0 z-20 w-12 min-w-12 border-b border-outline-variant px-1 py-2 text-center {{ $day->isToday() ? 'bg-today' : ($isRed ? 'bg-day-off' : 'bg-surface-lowest') }}">
                                <div class="text-sm font-medium text-on-surface">{{ $day->day }}</div>
                                <div class="text-[10px] text-on-surface-variant">{{ $day->format('D') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <th class="sticky left-0 z-10 w-36 min-w-36 border-b border-outline-variant bg-surface-lowest px-3 py-2 text-left shadow-[4px_0_8px_-6px_rgba(15,23,42,0.28)] sm:w-48">
                                <div class="font-medium text-on-surface">{{ $user->name }}</div>
                                <div class="font-normal text-on-surface-variant">{{ $user->job_title }}</div>
                            </th>
                            @foreach($days as $day)
                                @php
                                    $entry = $entries->get($user->id.'|'.$day->toDateString());
                                    $dayTasks = $tasks[$day->toDateString()] ?? collect();
                                    $holidayName = $dayTasks->where('kind', 'holiday')->pluck('title')->implode(', ');
                                    $isRed = $day->isWeekend() || $holidayName !== '';
                                @endphp
                                <td class="border-b border-outline-variant p-1 text-center align-middle {{ $day->isToday() ? 'bg-today' : ($isRed ? 'bg-day-off' : 'bg-surface-lowest') }}">
                                    @if($isManager)
                                        <button
                                            type="button"
                                            @if($holidayName !== '') title="{{ $holidayName }}" @endif
                                            data-cell="{{ $user->id }}-{{ $day->toDateString() }}"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-date="{{ $day->toDateString() }}"
                                            data-date-label="{{ $day->format('D, j M Y') }}"
                                            data-status="{{ $entry->status ?? '' }}"
                                            x-on:click="openCell($event.currentTarget)"
                                            class="flex h-10 w-full items-center justify-center rounded-md hover:bg-on-surface/5"
                                        >
                                            @if($entry)
                                                <span data-badge class="roster-badge {{ $badges[$entry->status] ?? '' }}">{{ $labels[$entry->status] ?? $entry->status }}</span>
                                            @else
                                                <span data-badge class="text-[11px] text-outline">+</span>
                                            @endif
                                        </button>
                                    @else
                                        <div class="flex h-10 w-full items-center justify-center">
                                            @if($entry)
                                                <span class="roster-badge {{ $badges[$entry->status] ?? '' }}">{{ $labels[$entry->status] ?? $entry->status }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($days) + 1 }}" class="px-4 py-10 text-center text-sm text-on-surface-variant">No active employees yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($todayInMonth)
            <section class="m3-card mt-4 !p-4">
                <h2 class="text-sm font-medium">Today, {{ $today->format('l, j F') }}</h2>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    @foreach(['SHIFT', 'OFF', 'AL', 'DP', 'SL', 'PH', 'EMPTY'] as $group)
                        @if(! empty($todayGroups[$group]))
                            <p><span class="text-on-surface-variant">{{ $group === 'EMPTY' ? 'Not set' : $statusNames[$group] }}:</span> {{ implode(', ', $todayGroups[$group]) }}</p>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
        </section>

        @if($isManager)
            <div x-show="open" x-cloak class="m3-scrim" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <form class="m3-sheet" x-on:submit.prevent="save">
                    <div class="m3-progress" x-show="saving" x-cloak><span></span></div>
                    <div class="m3-handle"></div>
                    <h2 class="text-[22px] leading-7">Edit day</h2>
                    <p class="mt-1 text-sm text-on-surface-variant"><span x-text="userName"></span> · <span x-text="dateLabel"></span></p>
                    <label class="mt-5 block text-sm">
                        <span class="m3-label">Status</span>
                        <select x-model="status" class="m3-field">
                            <option value="SHIFT">ON · Working</option>
                            <option value="OFF">OFF · Off</option>
                            <option value="AL">AL · Annual leave</option>
                            <option value="DP">DP · Day pending</option>
                            <option value="SL">SL · Sick leave</option>
                            <option value="PH">PH · Public holiday</option>
                        </select>
                    </label>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-text state-layer" x-on:click="open = false">Cancel</button>
                        <button type="submit" class="btn-filled state-layer disabled:opacity-60" x-bind:disabled="saving" x-text="saving ? 'Saving…' : 'Save'"></button>
                    </div>
                </form>
            </div>
        @endif

        <section class="mt-6">
            <h2 class="text-base font-medium">Calendar & Tasks</h2>
            <p class="mt-1 mb-3 text-sm text-on-surface-variant">Hover or tap a date, then Add. Tap an item to edit, remove, or mark a task complete.</p>
            @php
                $monthHolidays = collect($tasks)->flatten(1)->where('kind', 'holiday')->sortBy('date');
            @endphp
            @if($monthHolidays->isNotEmpty())
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach($monthHolidays as $holiday)
                        <span class="m3-chip border-0 bg-error-container text-on-error-container">
                            <span class="font-medium">{{ $holiday->date->format('j M') }}</span> {{ $holiday->title }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="m3-card overflow-hidden !p-0">
            <div class="grid grid-cols-7">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="border-r border-b border-outline-variant px-1 py-2 text-center text-[11px] font-medium tracking-wide text-on-surface-variant nth-[7n]:border-r-0 {{ in_array($weekday, ['Sat', 'Sun'], true) ? 'bg-day-off' : 'bg-surface-lowest' }}">{{ $weekday }}</div>
                @endforeach
                @for($blank = 0; $blank < $leadingBlankDays; $blank++)
                    <div class="min-h-16 border-r border-b border-outline-variant nth-[7n]:border-r-0 {{ $blank >= 5 ? 'bg-day-off' : 'bg-surface-lowest' }}"></div>
                @endfor
                @foreach($days as $day)
                    @php
                        $dayTasks = $tasks[$day->toDateString()] ?? collect();
                        $isRed = $day->isWeekend() || $dayTasks->contains(fn ($task) => $task->kind === 'holiday');
                        $cellBg = $day->isToday() ? 'bg-today' : ($day->isWeekend() ? 'bg-day-off' : 'bg-surface-lowest');
                    @endphp
                    <div @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif class="group flex min-h-16 flex-col border-r border-b border-outline-variant p-1.5 nth-[7n]:border-r-0 {{ $cellBg }}" x-on:click="if ($event.target.closest('button')) return; addHint = @js($day->toDateString())">
                        <div class="flex h-6 items-center justify-between gap-1 px-0.5">
                            <span class="text-sm font-medium text-on-surface">{{ $day->day }}</span>
                            <button
                                type="button"
                                class="pointer-events-none rounded-md px-1 text-[10px] font-semibold text-primary opacity-0 group-hover:pointer-events-auto group-hover:opacity-100 focus-visible:pointer-events-auto focus-visible:opacity-100"
                                :class="addHint === @js($day->toDateString()) && '!pointer-events-auto !opacity-100'"
                                x-on:click.stop="openTask(@js($day->toDateString()), @js($day->format('l, j F Y')))"
                            >+ Add</button>
                        </div>
                        <div class="space-y-1">
                            @php
                                $tasksOnDay = $dayTasks->where('kind', 'task')->values();
                                $tasksGrouped = false;
                            @endphp
                            @foreach($dayTasks as $task)
                                @if($task->kind === 'task' && $tasksOnDay->count() > 1)
                                    @if(! $tasksGrouped)
                                        @php $tasksGrouped = true; @endphp
                                        <div class="rounded-lg bg-success-container px-2 py-1.5 text-xs leading-snug text-on-success-container">
                                            <span class="block text-[10px] font-medium tracking-wide">Task</span>
                                            <ol class="mt-0.5 list-decimal space-y-1 pl-4">
                                                @foreach($tasksOnDay as $groupedTask)
                                                    <li class="break-words whitespace-normal marker:font-medium {{ $groupedTask->is_done ? 'line-through opacity-60' : '' }}">
                                                        <button
                                                            type="button"
                                                            class="w-full text-left font-medium"
                                                            x-on:click="openEdit(@js(['id' => $groupedTask->id, 'date' => $groupedTask->date->toDateString(), 'label' => $day->format('l, j F Y'), 'title' => $groupedTask->title, 'kind' => $groupedTask->kind, 'done' => (bool) $groupedTask->is_done]))"
                                                        >{{ $groupedTask->title }}</button>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    @endif
                                @else
                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-2 py-1.5 text-left text-xs leading-snug break-words whitespace-normal {{ $chipFor($task->kind) }} {{ $task->is_done ? 'line-through opacity-60' : '' }}"
                                        x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $day->format('l, j F Y'), 'title' => $task->title, 'kind' => $task->kind, 'done' => (bool) $task->is_done]))"
                                    >
                                        <span class="block text-[10px] font-medium tracking-wide">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                        <span class="block font-medium">{{ $task->title }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @php $filled = ($leadingBlankDays + count($days)) % 7; @endphp
                @for($blank = 0; $blank < (7 - $filled) % 7; $blank++)
                    <div class="min-h-16 border-r border-b border-outline-variant nth-[7n]:border-r-0 {{ ($filled + $blank) >= 5 ? 'bg-day-off' : 'bg-surface-lowest' }}"></div>
                @endfor
            </div>
            </div>

            <div class="m3-card mt-6">
                <h3 class="text-base font-medium">Upcoming</h3>
                <p class="mt-1 text-sm text-on-surface-variant">Events, tasks, and public holidays in {{ $next->format('F Y') }}.</p>
                @if($upcoming->isEmpty())
                    <p class="mt-3 text-sm text-on-surface-variant">Nothing in {{ $next->format('F Y') }}.</p>
                @else
                    <ul class="mt-2">
                        @php
                            $upcomingByDate = $upcoming->groupBy(fn ($task) => $task->date->toDateString());
                        @endphp
                        @foreach($upcomingByDate as $items)
                            @php
                                $dateTasks = $items->where('kind', 'task')->values();
                                $tasksListed = false;
                            @endphp
                            @foreach($items as $task)
                                @if($task->kind === 'task' && $dateTasks->count() > 1)
                                    @if(! $tasksListed)
                                        @php $tasksListed = true; @endphp
                                        <li class="rounded-2xl px-2 py-2 row-hover">
                                            <div class="text-sm">
                                                <span class="font-medium">{{ $dateTasks->first()->date->format('j M Y') }}</span>
                                                <span class="ml-2 m3-chip border-0 bg-success-container text-on-success-container">Task</span>
                                            </div>
                                            <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm">
                                                @foreach($dateTasks as $groupedTask)
                                                    <li class="break-words whitespace-normal {{ $groupedTask->is_done ? 'line-through opacity-60' : '' }}">
                                                        <button
                                                            type="button"
                                                            class="w-full text-left"
                                                            x-on:click="openEdit(@js(['id' => $groupedTask->id, 'date' => $groupedTask->date->toDateString(), 'label' => $groupedTask->date->format('l, j F Y'), 'title' => $groupedTask->title, 'kind' => $groupedTask->kind, 'done' => (bool) $groupedTask->is_done]))"
                                                        >{{ $groupedTask->title }}</button>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </li>
                                    @endif
                                @else
                                    <li>
                                        <button
                                            type="button"
                                            class="row-hover w-full break-words whitespace-normal rounded-2xl px-2 py-2 text-left text-sm {{ $task->is_done ? 'line-through opacity-60' : '' }}"
                                            x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $task->date->format('l, j F Y'), 'title' => $task->title, 'kind' => $task->kind, 'done' => (bool) $task->is_done]))"
                                        >
                                            <span class="font-medium">{{ $task->date->format('j M Y') }}</span>
                                            <span class="ml-2 m3-chip border-0 {{ $chipFor($task->kind) }}">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                            <span class="ml-2">{{ $task->title }}</span>
                                        </button>
                                    </li>
                                @endif
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <button type="button" class="btn-fab state-layer" x-on:click="openTask(@js($today->toDateString()), @js($today->format('l, j F Y')))">
            <x-icon name="add" />
            Add item
        </button>

        <div x-show="taskOpen" x-cloak class="m3-scrim" x-on:click.self="taskOpen = false" x-on:keydown.escape.window="taskOpen = false">
            <div class="m3-sheet sm:max-w-sm">
                <div class="m3-handle"></div>
                <form method="POST" x-bind:action="taskAction">
                    @csrf
                    <template x-if="taskEditing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>
                    <input type="hidden" name="form" value="task">
                    <input type="hidden" name="date" x-bind:value="taskDate">
                    <h2 class="text-[22px] leading-7" x-text="taskEditing ? 'Edit item' : 'Add to this day'"></h2>
                    <p class="mt-1 text-sm text-on-surface-variant" x-text="taskDateLabel"></p>
                    <div class="mt-5 flex gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="kind" value="event" class="sr-only" x-model="taskKind">
                            <span class="m3-chip state-layer" :class="taskKind === 'event' && 'm3-chip-selected'">Event</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="kind" value="task" class="sr-only" x-model="taskKind">
                            <span class="m3-chip state-layer" :class="taskKind === 'task' && 'm3-chip-selected'">Task</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="kind" value="holiday" class="sr-only" x-model="taskKind">
                            <span class="m3-chip state-layer" :class="taskKind === 'holiday' && 'm3-chip-selected'">Holiday</span>
                        </label>
                    </div>
                    <label class="relative mt-4 block text-sm">
                        <input type="text" name="title" x-model="taskTitle" required maxlength="120" placeholder=" " class="peer m3-field">
                        <span class="m3-float">Title</span>
                    </label>
                    <template x-if="taskEditing && taskKind === 'task'">
                        <label class="mt-4 flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" name="is_done" value="1" class="m3-check" x-model="taskDone">
                            <span>Mark as complete</span>
                        </label>
                    </template>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-text state-layer" x-on:click="taskOpen = false">Cancel</button>
                        <button type="submit" class="btn-filled state-layer" x-text="taskEditing ? 'Save' : 'Add'"></button>
                    </div>
                </form>
                <form x-show="taskEditing" x-cloak method="POST" x-bind:action="taskAction" class="mt-2" onsubmit="return confirm('Remove this from the calendar?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-text state-layer w-full text-error">Delete</button>
                </form>
            </div>
        </div>
    </div>
@endsection
