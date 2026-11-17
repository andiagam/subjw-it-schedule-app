@extends('layouts.app')

@section('title', $start->format('F Y'))

@section('content')
    @php
        $isManager = $currentUser?->isManager() ?? false;
        $blocks = [
            'SHIFT' => 'bg-emerald-200/85 text-emerald-950 dark:bg-emerald-900/75 dark:text-emerald-100',
            'OFF' => 'bg-red-200/85 text-red-950 dark:bg-red-900/75 dark:text-red-100',
            'AL' => 'bg-amber-200/90 text-amber-950 dark:bg-amber-900/75 dark:text-amber-100',
            'DP' => 'bg-blue-200/85 text-blue-950 dark:bg-blue-900/75 dark:text-blue-100',
            'SL' => 'bg-orange-200/90 text-orange-950 dark:bg-orange-900/75 dark:text-orange-100',
            'PH' => 'bg-purple-200/85 text-purple-950 dark:bg-purple-900/75 dark:text-purple-100',
            'BLACK' => 'bg-neutral-950 text-white hover:bg-neutral-800 dark:bg-black dark:border-neutral-700',
            '' => 'bg-white text-slate-400 dark:bg-[#1E1F23]',
        ];
        $labels = [
            'SHIFT' => 'ON',
            'OFF' => 'OFF',
            'AL' => 'AL',
            'DP' => 'DP',
            'SL' => 'SL',
            'PH' => 'PH',
            'BLACK' => '',
            '' => '+',
        ];
        $legend = [
            'SHIFT' => 'Working',
            'OFF' => 'Off',
            'AL' => 'Annual leave',
            'DP' => 'Day pending',
            'SL' => 'Sick leave',
            'PH' => 'Public holiday',
            'BLACK' => 'Black shift (Ended)',
        ];
        $taskLabels = [
            'event' => 'Event',
            'task' => 'Task',
            'holiday' => 'Public holiday',
        ];
        $chipFor = fn ($kind) => $kind === 'event'
            ? 'bg-primary-container text-on-primary-container'
            : ($kind === 'holiday' ? 'bg-error-container text-on-error-container' : 'bg-success-container text-on-success-container');
        $markFor = fn ($kind) => $kind === 'event'
            ? 'bg-blue-500'
            : ($kind === 'holiday' ? 'bg-purple-600' : 'bg-emerald-500');
        $today = \Carbon\CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $activityLabels = [
            'added' => 'Added',
            'edited' => 'Edited',
            'completed' => 'Completed',
            'uncompleted' => 'Uncompleted',
            'removed' => 'Removed',
        ];
    @endphp

    <div x-data="rosterPage(@js([
        'taskOpen' => $errors->any() && old('form') === 'task',
        'taskDate' => old('date', ''),
        'taskDateLabel' => old('date', ''),
        'taskKind' => old('kind', 'task'),
        'taskTitle' => old('title', ''),
        'today' => $today->toDateString(),
        'todayLabel' => $today->format('l, j F Y'),
        'actorName' => $currentUser->name,
        'upcomingStart' => $upcomingStart,
        'upcomingEnd' => $upcomingEnd,
    ]))">
        @php
            $todayInMonth = $today->betweenIncluded($start, $start->endOfMonth());
            $todayKey = $today->toDateString();
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
                    if ($key === 'BLACK') {
                        continue;
                    }
                    $todayGroups[$key][] = $person->name;
                }
            }
        @endphp

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <x-month-nav :start="$start" :previous="$previous" :next="$next" />
            @if($isManager)
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-outlined state-layer h-9 px-4 text-sm" :class="multiMode && 'border-primary text-primary'" x-on:click="multiMode = !multiMode" x-text="multiMode ? 'Selecting' : 'Multi-select'">Multi-select</button>
                    <form method="POST" action="{{ route('roster.autofill') }}" onsubmit="return confirm('Fill this month from each person\'s default week? Empty cells and regular ON/OFF days are updated. Annual leave, sick leave, day pending, public holidays, and black shifts stay as they are. Days after an end date become black.')">
                        @csrf
                        <input type="hidden" name="month" value="{{ $start->month }}">
                        <input type="hidden" name="year" value="{{ $start->year }}">
                        <button type="submit" class="btn-tonal state-layer h-9 px-4 text-sm">Auto-fill</button>
                    </form>
                    <a href="{{ route('roster.export', ['month' => $start->month, 'year' => $start->year]) }}" class="btn-outlined state-layer h-9 px-4 text-sm">Export</a>
                </div>
            @endif
        </div>

        <section>
        <h2 class="mb-3 text-sm font-medium">Shift Roster</h2>
        <div class="mb-3 hidden flex-wrap items-center gap-x-4 gap-y-2 text-xs text-on-surface-variant sm:flex">
            @foreach($legend as $status => $name)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded px-1 text-[10px] font-bold {{ $blocks[$status] }}">{{ $labels[$status] }}</span>
                    {{ $name }}
                </span>
            @endforeach
        </div>

        <div data-month-board data-roster-scroll class="relative z-0 m3-card w-full max-h-[70vh] overflow-x-auto overflow-y-auto overscroll-x-contain !p-0" style="-webkit-overflow-scrolling: touch;">
            <table class="min-w-max w-full border-separate border-spacing-0 text-xs">
                <thead>
                    <tr>
                        <th class="sticky top-0 left-0 z-30 w-36 min-w-36 border-r-2 border-b border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-600 dark:bg-[#1E1F23] sm:w-48">Employee</th>
                        @foreach($days as $day)
                            @php
                                $dayTasks = $tasks[$day->toDateString()] ?? collect();
                                $holidayName = $dayTasks->where('kind', 'holiday')->pluck('title')->implode(', ');
                                $isRed = $day->isWeekend() || $holidayName !== '';
                            @endphp
                            <th @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif @if($day->isToday()) data-today="1" @endif @if($holidayName !== '') title="{{ $holidayName }}" @endif class="sticky top-0 z-20 w-12 min-w-12 border-b border-outline-variant px-1 py-2 text-center {{ $day->isToday() ? 'bg-today' : ($isRed ? 'bg-day-off' : 'bg-white dark:bg-[#1E1F23]') }}">
                                <div class="text-sm font-medium text-on-surface">{{ $day->day }}</div>
                                <div class="text-[10px] text-on-surface-variant">{{ $day->format('D') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <th class="sticky left-0 z-10 w-36 min-w-36 border-r-2 border-b border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-600 dark:bg-[#1E1F23] sm:w-48">
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
                                @php
                                    $cellStatus = $entry->status ?? '';
                                    $cellText = $labels[$cellStatus] ?? ($cellStatus === '' ? '+' : $cellStatus);
                                @endphp
                                <td class="border-r border-b border-slate-200 p-0 align-middle dark:border-slate-700/80 {{ $day->isToday() ? 'ring-2 ring-inset ring-blue-600 dark:ring-blue-400' : '' }}">
                                    @if($isManager)
                                        <button
                                            type="button"
                                            @if($holidayName !== '') title="{{ $holidayName }}" @endif
                                            data-cell="{{ $user->id }}-{{ $day->toDateString() }}"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-date="{{ $day->toDateString() }}"
                                            data-date-label="{{ $day->format('D, j M Y') }}"
                                            data-status="{{ $cellStatus }}"
                                            x-on:pointerdown="startDrag($event)"
                                            x-on:click="chooseCell($event)"
                                            class="roster-block h-full min-h-[46px] w-full border-0 p-0 flex items-center justify-center text-xs font-bold tracking-wide {{ $blocks[$cellStatus] ?? $blocks[''] }}"
                                        >{{ $cellText }}</button>
                                    @else
                                        <div class="roster-block flex h-full min-h-[46px] w-full items-center justify-center p-0 text-xs font-bold tracking-wide {{ $blocks[$cellStatus] ?? $blocks[''] }}">{{ $cellText }}</div>
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

        @if($isManager)
            <div x-show="selectedDates.length > 0" x-cloak class="fixed bottom-4 left-1/2 z-[70] flex w-[min(100%-1.5rem,56rem)] -translate-x-1/2 flex-wrap items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 shadow-lg dark:border-slate-700 dark:bg-[#1E1F23]">
                <span class="px-2 text-sm font-medium" x-text="selectedDates.length + (selectedDates.length === 1 ? ' day' : ' days') + ' selected for ' + selectedUserName"></span>
                <button type="button" class="rounded-full bg-emerald-200/85 px-3 py-1.5 text-xs font-bold text-emerald-950" x-on:click="applyBulk('SHIFT')">ON</button>
                <button type="button" class="rounded-full bg-red-200/85 px-3 py-1.5 text-xs font-bold text-red-950" x-on:click="applyBulk('OFF')">OFF</button>
                <button type="button" class="rounded-full bg-amber-200/90 px-3 py-1.5 text-xs font-bold text-amber-950" x-on:click="applyBulk('AL')">AL</button>
                <button type="button" class="rounded-full bg-orange-200/90 px-3 py-1.5 text-xs font-bold text-orange-950" x-on:click="applyBulk('SL')">SL</button>
                <button type="button" class="rounded-full bg-purple-200/85 px-3 py-1.5 text-xs font-bold text-purple-950" x-on:click="applyBulk('PH')">PH</button>
                <button type="button" class="rounded-full bg-neutral-950 px-3 py-1.5 text-xs font-bold text-white" x-on:click="applyBulk('BLACK')">Black Shift</button>
                <button type="button" class="btn-outlined state-layer h-8 px-3 text-xs" x-on:click="applyBulk(null)">Clear</button>
                <button type="button" class="btn-text state-layer h-8 px-3 text-xs" x-on:click="clearSelection()">Cancel</button>
            </div>
        @endif

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
                            <option value="BLACK">Black shift · Ended</option>
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
            <h2 class="mb-3 text-base font-medium">Calendar & Tasks</h2>
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
                    <div class="min-h-[68px] border-r border-b border-outline-variant sm:min-h-16 nth-[7n]:border-r-0 {{ $blank >= 5 ? 'bg-day-off' : 'bg-surface-lowest' }}"></div>
                @endfor
                @foreach($days as $day)
                    @php
                        $dayTasks = $tasks[$day->toDateString()] ?? collect();
                        $isRed = $day->isWeekend() || $dayTasks->contains(fn ($task) => $task->kind === 'holiday');
                        $cellBg = $day->isToday() ? 'bg-today' : ($day->isWeekend() ? 'bg-day-off' : 'bg-surface-lowest');
                    @endphp
                    <div data-calendar-day="{{ $day->toDateString() }}" @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif class="group flex min-h-[68px] cursor-pointer flex-col border-r border-b border-outline-variant p-1 nth-[7n]:border-r-0 sm:min-h-16 sm:cursor-default sm:p-1.5 {{ $cellBg }}" x-on:click="openCalendarDay($event, @js($day->toDateString()), @js($day->format('l, j F Y')))">
                        <div class="flex h-6 items-center justify-between gap-1 px-0.5">
                            <span class="text-sm font-medium text-on-surface">{{ $day->day }}</span>
                            <button
                                type="button"
                                class="pointer-events-none hidden rounded-md px-1 text-[10px] font-semibold text-primary opacity-0 group-hover:pointer-events-auto group-hover:opacity-100 focus-visible:pointer-events-auto focus-visible:opacity-100 sm:block"
                                :class="addHint === @js($day->toDateString()) && '!pointer-events-auto !opacity-100'"
                                x-on:click.stop="openTask(@js($day->toDateString()), @js($day->format('l, j F Y')))"
                            >+ Add</button>
                        </div>
                        <div data-day-marks class="mt-auto flex items-center gap-1 sm:hidden" aria-hidden="true">
                            @foreach($dayTasks->take(2) as $mark)
                                <span class="h-1.5 min-w-2 flex-1 rounded-full {{ $markFor($mark->kind) }}"></span>
                            @endforeach
                            @if($dayTasks->count() > 2)
                                <span class="text-[10px] font-semibold leading-none text-on-surface-variant">+{{ $dayTasks->count() - 2 }}</span>
                            @endif
                        </div>
                        <div class="hidden space-y-1 sm:block" data-day-items>
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
                                                    <li class="marker:font-medium {{ $groupedTask->is_done ? 'line-through opacity-60' : '' }}">
                                                        <button
                                                            type="button"
                                                            data-task-id="{{ $groupedTask->id }}"
                                                            data-task-title
                                                            data-kind="task"
                                                            data-done="{{ $groupedTask->is_done ? '1' : '0' }}"
                                                            class="line-clamp-2 w-full break-normal text-left font-medium {{ $groupedTask->is_done ? 'line-through opacity-60' : '' }}"
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
                                        data-task-id="{{ $task->id }}"
                                        data-kind="{{ $task->kind }}"
                                        data-done="{{ $task->is_done ? '1' : '0' }}"
                                        class="w-full rounded-lg px-2 py-1.5 text-left text-xs leading-snug {{ $chipFor($task->kind) }} {{ $task->is_done ? 'line-through opacity-60' : '' }}"
                                        x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $day->format('l, j F Y'), 'title' => $task->title, 'kind' => $task->kind, 'done' => (bool) $task->is_done]))"
                                    >
                                        <span class="block text-[10px] font-medium tracking-wide">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                        <span class="line-clamp-2 block break-normal font-medium" data-task-title>{{ $task->title }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @php $filled = ($leadingBlankDays + count($days)) % 7; @endphp
                @for($blank = 0; $blank < (7 - $filled) % 7; $blank++)
                    <div class="min-h-[68px] border-r border-b border-outline-variant sm:min-h-16 nth-[7n]:border-r-0 {{ ($filled + $blank) >= 5 ? 'bg-day-off' : 'bg-surface-lowest' }}"></div>
                @endfor
            </div>
            </div>

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-[#1E1F23]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-medium">{{ "Today's Tasks" }}</h3>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-text state-layer h-9 px-3 text-sm" x-on:click="activityOpen = true">
                            <x-icon name="history" class="text-[18px]" />
                            Activity Log
                        </button>
                        <button type="button" class="btn-tonal state-layer h-9 px-3 text-sm" x-on:click="openTask(@js($today->toDateString()), @js($today->format('l, j F Y')))">+ Add task</button>
                    </div>
                </div>
                <ul data-today-tasks class="mt-3 space-y-2">
                    @forelse($todayTasks as $task)
                        @php
                            $byline = collect([
                                $task->created_by_name ? 'Added by '.$task->created_by_name : null,
                                $task->updated_by_name ? 'Edited by '.$task->updated_by_name : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <li data-today-task="{{ $task->id }}" data-date="{{ $task->date->toDateString() }}" data-created-by="{{ $task->created_by_name }}" data-updated-by="{{ $task->updated_by_name }}" class="flex items-start gap-2 rounded-xl px-1 py-1">
                            <input type="checkbox" class="m3-check mt-1" @checked($task->is_done) aria-label="Mark {{ $task->title }} complete" x-on:change="toggleTodayTask($event.target)">
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    data-task-id="{{ $task->id }}"
                                    data-task-title
                                    class="w-full text-left text-sm font-medium break-words {{ $task->is_done ? 'line-through opacity-60' : '' }}"
                                    x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $today->format('l, j F Y'), 'title' => $task->title, 'kind' => 'task', 'done' => (bool) $task->is_done]))"
                                >{{ $task->title }}</button>
                                <p data-task-byline class="text-xs text-on-surface-variant" @if($byline === '') hidden @endif>{{ $byline }}</p>
                            </div>
                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high" aria-label="Edit {{ $task->title }}" x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $today->format('l, j F Y'), 'title' => $task->title, 'kind' => 'task', 'done' => (bool) $task->is_done]))">
                                <x-icon name="edit" class="text-[18px]" />
                            </button>
                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-error hover:bg-error-container" aria-label="Remove {{ $task->title }}" x-on:click="removeTodayTask(@js($task->id))">
                                <x-icon name="delete" class="text-[18px]" />
                            </button>
                        </li>
                    @empty
                        <li data-today-empty class="text-sm text-on-surface-variant">No tasks for today.</li>
                    @endforelse
                </ul>
            </section>

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-[#1E1F23]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-medium">{{ "Today's Events" }}</h3>
                    <button type="button" class="btn-tonal state-layer h-9 px-3 text-sm" x-on:click="openEvent(@js($today->toDateString()), @js($today->format('l, j F Y')))">+ Add event</button>
                </div>
                <ul data-today-events class="mt-3 space-y-2">
                    @forelse($todayEvents as $event)
                        @php
                            $eventByline = collect([
                                $event->created_by_name ? 'Added by '.$event->created_by_name : null,
                                $event->updated_by_name ? 'Edited by '.$event->updated_by_name : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <li data-today-event="{{ $event->id }}" data-date="{{ $event->date->toDateString() }}" data-created-by="{{ $event->created_by_name }}" data-updated-by="{{ $event->updated_by_name }}" class="flex items-start gap-2 rounded-xl px-1 py-1">
                            <span class="m3-chip mt-0.5 shrink-0 border-0 bg-primary-container text-on-primary-container">Event</span>
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    data-task-id="{{ $event->id }}"
                                    data-task-title
                                    class="w-full text-left text-sm font-medium break-words"
                                    x-on:click="openEdit(@js(['id' => $event->id, 'date' => $event->date->toDateString(), 'label' => $today->format('l, j F Y'), 'title' => $event->title, 'kind' => 'event', 'done' => false]))"
                                >{{ $event->title }}</button>
                                <p data-task-byline class="text-xs text-on-surface-variant" @if($eventByline === '') hidden @endif>{{ $eventByline }}</p>
                            </div>
                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high" aria-label="Edit {{ $event->title }}" x-on:click="openEdit(@js(['id' => $event->id, 'date' => $event->date->toDateString(), 'label' => $today->format('l, j F Y'), 'title' => $event->title, 'kind' => 'event', 'done' => false]))">
                                <x-icon name="edit" class="text-[18px]" />
                            </button>
                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-error hover:bg-error-container" aria-label="Remove {{ $event->title }}" x-on:click="removeTodayEvent(@js($event->id))">
                                <x-icon name="delete" class="text-[18px]" />
                            </button>
                        </li>
                    @empty
                        <li data-today-events-empty class="text-sm text-on-surface-variant">No events for today.</li>
                    @endforelse
                </ul>
            </section>

            <div class="m3-card mt-6">
                <h3 class="text-base font-medium">Upcoming</h3>
                <ul data-upcoming class="mt-2">
                    @forelse($upcoming as $task)
                        <li data-id="{{ $task->id }}" data-date="{{ $task->date->toDateString() }}" data-upcoming-id="{{ $task->id }}">
                            <button
                                type="button"
                                data-task-id="{{ $task->id }}"
                                data-kind="{{ $task->kind }}"
                                data-done="{{ $task->is_done ? '1' : '0' }}"
                                data-date="{{ $task->date->toDateString() }}"
                                class="row-hover w-full rounded-2xl px-2 py-2 text-left text-sm {{ $task->is_done ? 'line-through opacity-60' : '' }}"
                                x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $task->date->format('l, j F Y'), 'title' => $task->title, 'kind' => $task->kind, 'done' => (bool) $task->is_done]))"
                            >
                                <span class="font-medium" data-upcoming-date>{{ $task->date->format('j M Y') }}</span>
                                <span data-upcoming-kind class="ml-2 m3-chip border-0 {{ $chipFor($task->kind) }}">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                <span class="ml-2" data-task-title>{{ $task->title }}</span>
                            </button>
                        </li>
                    @empty
                        <li data-upcoming-empty class="px-2 py-2 text-sm text-on-surface-variant">No upcoming events or tasks in the next 30 days.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <button type="button" class="btn-fab state-layer" x-on:click="openTask(@js($today->toDateString()), @js($today->format('l, j F Y')))">
            <x-icon name="add" />
            Add item
        </button>

        <div x-show="dayOpen" x-cloak class="m3-scrim sm:hidden" x-on:click.self="dayOpen = false" x-on:keydown.escape.window="dayOpen = false">
            <div class="m3-sheet" role="dialog" aria-modal="true" aria-labelledby="day-sheet-title">
                <div class="m3-handle"></div>
                <h2 id="day-sheet-title" class="text-[22px] leading-7" x-text="dayLabel"></h2>
                <ul data-day-sheet class="mt-4 max-h-[50vh] space-y-2 overflow-y-auto"></ul>
                <button type="button" class="btn-tonal state-layer mt-4 h-11 w-full text-sm" x-on:click="openTask(dayDate, dayLabel); dayOpen = false">+ Add item</button>
            </div>
        </div>

        <div x-show="taskOpen" x-cloak class="m3-scrim" x-on:click.self="taskOpen = false" x-on:keydown.escape.window="taskOpen = false">
            <div class="m3-sheet sm:max-w-sm">
                <div class="m3-handle"></div>
                <form method="POST" x-bind:action="taskAction" x-on:submit.prevent="saveTask">
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
                            <input type="checkbox" name="is_done" value="1" class="m3-check" x-model="taskDone" x-on:change="markTaskDone()">
                            <span>Mark as complete</span>
                        </label>
                    </template>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-text state-layer" x-on:click="taskOpen = false">Cancel</button>
                        <button type="submit" class="btn-filled state-layer" x-text="taskEditing ? 'Save' : 'Add'"></button>
                    </div>
                </form>
                <div x-show="taskEditing" x-cloak class="mt-2">
                    <button type="button" class="btn-text state-layer w-full text-error" x-on:click="deleteTask">Delete</button>
                </div>
            </div>
        </div>

        <div x-show="activityOpen" x-cloak class="m3-scrim" x-on:click.self="activityOpen = false" x-on:keydown.escape.window="activityOpen = false">
            <div class="m3-sheet sm:max-w-md">
                <div class="m3-handle"></div>
                <h2 class="text-[22px] leading-7">Activity Log</h2>
                <ul data-activity-log class="mt-4 max-h-80 space-y-1 overflow-y-auto">
                    @forelse($activityLogs as $log)
                        <li class="border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                            <span class="font-medium">{{ $activityLabels[$log->action] ?? ucfirst($log->action) }}</span>
                            <span class="text-on-surface-variant"> · {{ $log->task_title }}</span>
                            <span class="mt-0.5 block text-xs text-on-surface-variant">{{ $log->actor_name }} · {{ $log->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</span>
                        </li>
                    @empty
                        <li data-activity-empty class="text-sm text-on-surface-variant">No activity yet.</li>
                    @endforelse
                </ul>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn-text state-layer" x-on:click="activityOpen = false">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection
