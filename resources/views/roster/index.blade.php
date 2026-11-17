@extends('layouts.app')

@section('title', $start->format('F Y'))

@section('content')
    @php
        $isManager = $currentUser?->isManager() ?? false;
        $badges = [
            'SHIFT' => 'bg-emerald-100 text-emerald-800',
            'OFF' => 'bg-zinc-200 text-zinc-800',
            'AL' => 'bg-amber-100 text-amber-900',
            'DP' => 'bg-indigo-100 text-indigo-800',
            'SL' => 'bg-pink-100 text-pink-800',
            'PH' => 'bg-rose-200 text-rose-900',
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

        <div class="mb-4">
            <h1 class="text-xl font-semibold tracking-tight">Monthly roster</h1>
            <p class="mt-1 text-sm text-faint">The IT manager edits any schedule directly. Everyone else asks through Requests.</p>
            <p class="mt-1 text-sm text-ink">Your leave left: <span class="font-semibold">{{ $currentUser->al_balance }} annual</span> · <span class="font-semibold">{{ $currentUser->dp_balance }} day pending</span></p>
        </div>

        @if($todayInMonth)
            <section class="mb-4 rounded-2xl border border-line bg-surface p-4 shadow-sm">
                <h2 class="text-lg font-semibold">Today, {{ $today->format('l, j F Y') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach(['SHIFT', 'OFF', 'AL', 'DP', 'SL', 'PH', 'EMPTY'] as $group)
                        @if(! empty($todayGroups[$group]))
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-faint">{{ $group === 'EMPTY' ? 'Not set' : $statusNames[$group] }}</div>
                                <p class="mt-1 text-sm">{{ implode(', ', $todayGroups[$group]) }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-faint">Today's tasks</div>
                    @php $openToday = $todayTasks->where('is_done', false); @endphp
                    @if($openToday->isEmpty())
                        <p class="mt-1 text-sm text-faint">No open tasks.</p>
                    @else
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach($openToday as $task)
                                <li>{{ $taskLabels[$task->kind] ?? 'Task' }}: {{ $task->title }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif

        <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-line bg-surface p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('roster.index', ['month' => $previous->month, 'year' => $previous->year]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold hover:bg-surface-2">Previous</a>
            <div class="text-center text-2xl font-semibold tracking-tight">{{ $start->format('F Y') }}</div>
            <div class="flex gap-2">
                <a href="{{ route('roster.index', ['month' => $next->month, 'year' => $next->year]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold hover:bg-surface-2 sm:flex-none">Next</a>
                <a href="{{ route('roster.index') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-line px-4 text-sm font-semibold hover:bg-surface-2 sm:flex-none">Today</a>
            </div>
        </div>

        @if($isManager)
            <div class="mb-4 flex flex-col gap-2 sm:flex-row">
                <form method="POST" action="{{ route('roster.autofill') }}" class="sm:w-auto" onsubmit="return confirm('Fill empty days this month? Weekdays become ON, weekends become OFF. Public holidays and existing entries stay as they are.')">
                    @csrf
                    <input type="hidden" name="month" value="{{ $start->month }}">
                    <input type="hidden" name="year" value="{{ $start->year }}">
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-button px-4 text-sm font-semibold text-button-ink hover:bg-white">Auto-fill month</button>
                </form>
                <a href="{{ route('roster.export', ['month' => $start->month, 'year' => $start->year]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-line bg-surface px-4 text-sm font-semibold hover:bg-surface-2">Export Excel</a>
            </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted">
            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">ON</span>
            <span>Working</span>
            <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-zinc-800">OFF</span>
            <span>Off</span>
            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900">AL</span>
            <span>Annual leave</span>
            <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-800">DP</span>
            <span>Day pending</span>
            <span class="inline-flex rounded-full bg-pink-100 px-2 py-0.5 text-[11px] font-semibold text-pink-800">SL</span>
            <span>Sick leave</span>
            <span class="inline-flex rounded-full bg-rose-200 px-2 py-0.5 text-[11px] font-semibold text-rose-900">PH</span>
            <span>Public holiday</span>
        </div>

        <details data-month-board open class="mb-4">
            <summary class="mb-3 cursor-pointer text-sm font-semibold md:hidden">Show the month board</summary>
        <div data-roster-scroll class="-mx-3 overflow-auto rounded-none border-y border-line bg-surface shadow-sm sm:mx-0 sm:rounded-2xl sm:border max-h-[70vh]">
            <table class="min-w-max border-separate border-spacing-0 text-xs">
                <thead>
                    <tr>
                        <th class="sticky top-0 left-0 z-30 w-28 min-w-28 border-r border-b border-line bg-surface-2 px-2 py-2 text-left sm:w-44 sm:min-w-44 sm:px-3">Employee</th>
                        @foreach($days as $day)
                            @php
                                $dayTasks = $tasks[$day->toDateString()] ?? collect();
                                $isRed = $day->isWeekend() || $dayTasks->contains(fn ($task) => $task->kind === 'holiday');
                            @endphp
                            <th @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif @if($day->isToday()) data-today="1" @endif class="sticky top-0 z-20 w-12 min-w-12 border-r border-b border-line px-1 py-2 text-center {{ $isRed ? 'bg-holiday text-rose-100' : 'bg-surface-2 text-muted' }} {{ $day->isToday() ? 'ring-2 ring-inset ring-sky-400' : '' }}">
                                <div class="text-sm font-semibold text-ink">{{ $day->day }}</div>
                                <div>{{ $day->format('D') }}</div>
                                @if($dayTasks->isNotEmpty())
                                    <div class="mt-0.5 text-[10px] font-semibold {{ $isRed ? 'text-rose-200' : 'text-sky-200' }}">{{ $dayTasks->count() }}</div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <th class="sticky left-0 z-10 w-28 min-w-28 border-r border-b border-line bg-surface px-2 py-2 text-left sm:w-44 sm:min-w-44 sm:px-3">
                                <div class="font-semibold text-ink">{{ $user->name }}</div>
                                <div class="hidden font-normal text-faint sm:block">{{ $user->job_title }}</div>
                            </th>
                            @foreach($days as $day)
                                @php
                                    $entry = $entries->get($user->id.'|'.$day->toDateString());
                                    $dayTasks = $tasks[$day->toDateString()] ?? collect();
                                    $isRed = $day->isWeekend() || $dayTasks->contains(fn ($task) => $task->kind === 'holiday');
                                @endphp
                                <td class="border-r border-b border-line p-0 align-middle {{ $isRed ? 'bg-holiday/80' : 'bg-surface' }} {{ $day->isToday() ? 'ring-2 ring-inset ring-sky-300' : '' }}">
                                    @if($isManager)
                                        <button
                                            type="button"
                                            data-cell="{{ $user->id }}-{{ $day->toDateString() }}"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-date="{{ $day->toDateString() }}"
                                            data-date-label="{{ $day->format('D, j M Y') }}"
                                            data-status="{{ $entry->status ?? '' }}"
                                            x-on:click="openCell($event.currentTarget)"
                                            class="flex min-h-11 w-full items-center justify-center px-1 py-1 hover:bg-white/10"
                                        >
                                            @if($entry)
                                                <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $badges[$entry->status] ?? '' }}">{{ $labels[$entry->status] ?? $entry->status }}</span>
                                            @else
                                                <span class="text-faint">+</span>
                                            @endif
                                        </button>
                                    @else
                                        <div class="flex min-h-11 items-center justify-center px-1 py-1">
                                            @if($entry)
                                                <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $badges[$entry->status] ?? '' }}">{{ $labels[$entry->status] ?? $entry->status }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($days) + 1 }}" class="px-4 py-10 text-center text-sm text-faint">No active employees yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </details>

        @if($isManager)
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center sm:p-4" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                <form class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-surface p-5 shadow-xl" x-on:submit.prevent="save">
                    <h2 class="text-lg font-semibold">Edit day</h2>
                    <p class="mt-1 text-sm text-faint"><span x-text="userName"></span> · <span x-text="dateLabel"></span></p>
                    <label class="mt-4 block text-sm">
                        <span class="mb-1 block font-medium">Status</span>
                        <select x-model="status" class="min-h-11 w-full rounded-lg border border-line px-3 py-2">
                            <option value="SHIFT">ON · Working</option>
                            <option value="OFF">OFF · Off</option>
                            <option value="AL">AL · Annual leave</option>
                            <option value="DP">DP · Day pending</option>
                            <option value="SL">SL · Sick leave</option>
                            <option value="PH">PH · Public holiday</option>
                        </select>
                    </label>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-muted hover:bg-surface-2" x-on:click="open = false">Cancel</button>
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-button px-4 text-sm font-semibold text-button-ink disabled:opacity-60" x-bind:disabled="saving" x-text="saving ? 'Saving…' : 'Save'"></button>
                    </div>
                </form>
            </div>
        @endif

        <section class="mt-8">
            <h2 class="mb-1 text-lg font-semibold">Calendar {{ $start->format('F Y') }}</h2>
            <p class="mb-3 text-sm text-faint">Tap a date to add an event, a task, or a public holiday.</p>

            <div class="flex flex-col gap-2 md:grid md:grid-cols-7">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="hidden px-1 text-center text-[11px] font-semibold uppercase tracking-wide text-faint md:block">{{ $weekday }}</div>
                @endforeach
                @for($blank = 0; $blank < $leadingBlankDays; $blank++)
                    <div class="hidden min-h-24 rounded-xl bg-surface-2 md:block"></div>
                @endfor
                @foreach($days as $day)
                    @php
                        $dayTasks = $tasks[$day->toDateString()] ?? collect();
                        $isRed = $day->isWeekend() || $dayTasks->contains(fn ($task) => $task->kind === 'holiday');
                    @endphp
                    <div @if($isRed) data-red-day="{{ $day->toDateString() }}" @endif class="flex min-h-16 flex-col rounded-xl border p-2 {{ $isRed ? 'border-rose-300/40 bg-holiday' : 'border-line bg-surface' }} {{ $day->isToday() ? 'ring-2 ring-sky-400' : '' }}">
                        <button type="button" class="flex h-8 w-full items-center justify-between text-left" x-on:click="openTask(@js($day->toDateString()), @js($day->format('l, j F Y')))">
                            <span>
                                <span class="text-sm font-semibold text-ink">{{ $day->day }}</span>
                                <span class="ml-1.5 text-xs text-faint md:hidden">{{ $day->format('D') }}</span>
                            </span>
                            <span class="text-[11px] font-semibold text-faint">Add</span>
                        </button>
                        <div class="space-y-1">
                            @foreach($dayTasks as $task)
                                @php
                                    $chip = $task->kind === 'event' ? 'bg-sky-100 text-sky-950' : ($task->kind === 'holiday' ? 'bg-rose-200 text-rose-950' : 'bg-emerald-100 text-emerald-950');
                                @endphp
                                <div class="flex items-center gap-1 rounded-md py-0.5 pr-0.5 pl-2 text-xs leading-tight {{ $chip }} {{ $task->is_done ? 'line-through opacity-60' : '' }}">
                                    <div class="min-w-0 flex-1 truncate">
                                        <span class="font-semibold">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                        <span class="opacity-50"> · </span>
                                        <span>{{ $task->title }}</span>
                                    </div>
                                    <x-task-actions :task="$task" :label="$day->format('l, j F Y')" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-2xl border border-line bg-surface p-4 shadow-sm">
                <h3 class="text-sm font-semibold">Upcoming</h3>
                <p class="mt-1 text-sm text-faint">Events, tasks, and public holidays outside {{ $start->format('F Y') }}.</p>
                @if($upcoming->isEmpty())
                    <p class="mt-3 text-sm text-faint">Nothing upcoming outside {{ $start->format('F') }}.</p>
                @else
                    <ul class="mt-2 divide-y divide-line">
                        @foreach($upcoming as $task)
                            <li class="flex items-center gap-2 py-1 text-sm">
                                <div class="min-w-0 flex-1">
                                    <span class="font-medium">{{ $task->date->format('j M Y') }}</span>
                                    <span class="ml-2 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $task->kind === 'holiday' ? 'bg-rose-200 text-rose-950' : ($task->kind === 'event' ? 'bg-sky-100 text-sky-950' : 'bg-emerald-100 text-emerald-950') }}">{{ $taskLabels[$task->kind] ?? 'Task' }}</span>
                                    <span class="ml-2">{{ $task->title }}</span>
                                </div>
                                <x-task-actions :task="$task" :label="$task->date->format('l, j F Y')" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <div x-show="taskOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center sm:p-4" x-on:click.self="taskOpen = false" x-on:keydown.escape.window="taskOpen = false">
            <form method="POST" x-bind:action="taskAction" class="w-full max-w-sm overflow-y-auto rounded-2xl bg-surface p-4 shadow-xl">
                @csrf
                <template x-if="taskEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="form" value="task">
                <input type="hidden" name="date" x-bind:value="taskDate">
                <h2 class="text-base font-semibold" x-text="taskEditing ? 'Edit item' : 'Add to this day'"></h2>
                <p class="mt-0.5 text-xs text-faint" x-text="taskDateLabel"></p>
                <div class="mt-4 grid grid-cols-3 gap-1 rounded-lg bg-field p-1 text-xs font-semibold">
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="event" class="sr-only" x-model="taskKind">
                        <span class="block rounded-md px-2 py-2 text-center" :class="taskKind === 'event' ? 'bg-sky-100 text-sky-950' : 'text-muted'">Event</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="task" class="sr-only" x-model="taskKind">
                        <span class="block rounded-md px-2 py-2 text-center" :class="taskKind === 'task' ? 'bg-emerald-100 text-emerald-950' : 'text-muted'">Task</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="kind" value="holiday" class="sr-only" x-model="taskKind">
                        <span class="block rounded-md px-2 py-2 text-center" :class="taskKind === 'holiday' ? 'bg-rose-200 text-rose-950' : 'text-muted'">Holiday</span>
                    </label>
                </div>
                <label class="mt-3 block text-sm">
                    <span class="mb-1 block text-xs font-medium text-faint">Title</span>
                    <input type="text" name="title" x-model="taskTitle" required maxlength="120" placeholder="Server patch, backup, national day" class="min-h-11 w-full rounded-lg border border-line bg-field px-3 py-2">
                </label>
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
                    <label class="flex items-center gap-2 text-sm" x-show="taskEditing" x-cloak>
                        <input type="checkbox" name="is_done" value="1" x-model="taskDone" x-bind:disabled="!taskEditing">
                        <span>Done</span>
                    </label>
                    <div class="ml-auto flex gap-2">
                        <button type="button" class="inline-flex h-9 items-center rounded-lg px-3 text-sm text-muted hover:bg-surface-2" x-on:click="taskOpen = false">Cancel</button>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-button px-3 text-sm font-semibold text-button-ink" x-text="taskEditing ? 'Save' : 'Add'"></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
