@extends('layouts.app')

@section('title', 'Requests')

@section('content')
    @php
        $typeLabels = [
            'AL' => 'Annual leave',
            'DP' => 'Day pending',
            'SL' => 'Sick leave',
            'SCHEDULE_CHANGE' => 'Schedule change',
        ];
        $filters = [
            'ALL' => 'All',
            'AL' => 'Annual leave',
            'DP' => 'Day pending',
            'SL' => 'Sick leave',
            'SCHEDULE_CHANGE' => 'Schedule',
        ];
    @endphp

    <div
        x-data="{
            open: {{ $errors->any() && old('form') === 'leave' ? 'true' : 'false' }},
            type: @js(old('type', 'AL')),
            start: @js(old('start_date', '')),
            end: @js(old('end_date', '')),
            filter: 'ALL',
            reviewOpen: false,
            reviewAction: 'approve',
            reviewType: 'AL',
            actionUrl: '',
            openReview(id, type, action) {
                this.reviewAction = action;
                this.reviewType = type;
                this.actionUrl = '{{ url('/requests') }}/' + id + '/' + action;
                this.reviewOpen = true;
                this.$nextTick(() => {
                    if (this.$refs.managerNote) this.$refs.managerNote.value = '';
                    if (this.$refs.creditDp) this.$refs.creditDp.checked = false;
                });
            }
        }"
    >
        <div class="mb-4">
            <p class="text-sm text-on-surface-variant">
                Annual leave left: <span class="font-medium text-on-surface">{{ $currentUser->al_balance }}</span>
                · Day pending left: <span class="font-medium text-on-surface">{{ $currentUser->dp_balance }}</span>
            </p>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach($filters as $value => $label)
                    <button type="button" class="m3-chip state-layer shrink-0" :class="filter === @js($value) && 'm3-chip-selected'" x-on:click="filter = @js($value)">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if($currentUser->isManager())
            <section class="m3-card mb-6">
                <div class="mb-3">
                    <h2 class="text-base font-medium">Approval inbox</h2>
                    <p class="text-sm text-on-surface-variant">{{ $pending->count() }} pending</p>
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-[720px] text-sm">
                        <thead class="text-left text-xs font-medium tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2">Employee</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Dates</th>
                                <th class="px-3 py-2">Reason</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending as $item)
                                <tr class="row-hover">
                                    <td class="px-3 py-3">
                                        <div class="font-medium">{{ $item->user->name }}</div>
                                        <div class="text-xs text-on-surface-variant">{{ $item->user->job_title }}</div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-status-pill :status="$item->type" />
                                        @if($item->type === 'SCHEDULE_CHANGE')
                                            <div class="mt-1 text-xs text-on-surface-variant">Change to {{ $item->requested_status }}</div>
                                        @else
                                            <div class="mt-1 text-xs text-on-surface-variant">{{ $item->dayCount() }} {{ $item->dayCount() === 1 ? 'day' : 'days' }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        {{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}
                                    </td>
                                    <td class="max-w-xs px-3 py-3 text-on-surface-variant">{{ $item->reason }}</td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <button type="button" class="btn-filled state-layer h-8 px-4 text-xs" x-on:click="openReview({{ $item->id }}, @js($item->type), 'approve')">Approve</button>
                                        <button type="button" class="btn-text state-layer h-8 text-xs text-error" x-on:click="openReview({{ $item->id }}, @js($item->type), 'reject')">Reject</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-10 text-center text-on-surface-variant">No requests waiting for review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="space-y-3 md:hidden">
                    @forelse($pending as $item)
                        <article class="rounded-2xl bg-surface-lowest p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ $item->user->name }}</div>
                                    <div class="text-xs text-on-surface-variant">{{ $item->user->job_title }}</div>
                                </div>
                                <x-status-pill :status="$item->type" />
                            </div>
                            <p class="mt-2 text-sm">{{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}</p>
                            <p class="mt-1 text-sm text-on-surface-variant">{{ $item->reason }}</p>
                            <div class="mt-3 flex justify-end gap-1">
                                <button type="button" class="btn-text state-layer text-error" x-on:click="openReview({{ $item->id }}, @js($item->type), 'reject')">Reject</button>
                                <button type="button" class="btn-filled state-layer" x-on:click="openReview({{ $item->id }}, @js($item->type), 'approve')">Approve</button>
                            </div>
                        </article>
                    @empty
                        <p class="py-8 text-center text-sm text-on-surface-variant">No requests waiting for review.</p>
                    @endforelse
                </div>
            </section>
        @endif

        <section class="m3-card">
            <h2 class="mb-3 text-base font-medium">{{ $currentUser->isManager() ? 'All requests' : 'My requests' }}</h2>
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-[720px] text-sm">
                    <thead class="text-left text-xs font-medium tracking-wide text-on-surface-variant">
                        <tr>
                            @if($currentUser->isManager())
                                <th class="px-3 py-2">Employee</th>
                            @endif
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Reason</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Manager note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $item)
                            <tr class="row-hover" x-show="filter === 'ALL' || filter === @js($item->type)">
                                @if($currentUser->isManager())
                                    <td class="px-3 py-3 font-medium">{{ $item->user->name }}</td>
                                @endif
                                <td class="px-3 py-3">
                                    {{ $typeLabels[$item->type] ?? $item->type }}
                                    @if($item->type === 'SCHEDULE_CHANGE')
                                        <div class="text-xs text-on-surface-variant">to {{ $item->requested_status }}@if($item->credit_dp) · +1 day pending @endif</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    {{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}
                                </td>
                                <td class="max-w-xs px-3 py-3 text-on-surface-variant">{{ $item->reason }}</td>
                                <td class="px-3 py-3"><x-status-pill :status="$item->status" /></td>
                                <td class="max-w-xs px-3 py-3 text-on-surface-variant">{{ $item->manager_note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $currentUser->isManager() ? 6 : 5 }}" class="px-3 py-10 text-center text-on-surface-variant">No requests yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="space-y-3 md:hidden">
                @forelse($history as $item)
                    <article class="rounded-2xl bg-surface-lowest p-4" x-show="filter === 'ALL' || filter === @js($item->type)">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                @if($currentUser->isManager())
                                    <div class="font-medium">{{ $item->user->name }}</div>
                                @endif
                                <div class="text-sm">{{ $typeLabels[$item->type] ?? $item->type }}</div>
                            </div>
                            <x-status-pill :status="$item->status" />
                        </div>
                        <p class="mt-2 text-sm">{{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}</p>
                        <p class="mt-1 text-sm text-on-surface-variant">{{ $item->reason }}</p>
                        @if($item->manager_note)
                            <p class="mt-2 text-sm">{{ $item->manager_note }}</p>
                        @endif
                    </article>
                @empty
                    <p class="py-8 text-center text-sm text-on-surface-variant">No requests yet.</p>
                @endforelse
            </div>
        </section>

        <button type="button" class="btn-fab state-layer" x-on:click="open = true">
            <x-icon name="add" />
            New request
        </button>

        <div x-show="open" x-cloak class="m3-scrim" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <form method="POST" action="{{ route('requests.store') }}" class="m3-sheet sm:max-w-lg">
                <div class="m3-handle"></div>
                @csrf
                <input type="hidden" name="form" value="leave">
                <h2 class="text-[22px] leading-7">New request</h2>
                <label class="mt-5 block text-sm">
                    <span class="m3-label">Type</span>
                    <select name="type" x-model="type" class="m3-field">
                        <option value="AL">Annual leave (AL)</option>
                        <option value="DP">Day pending (DP)</option>
                        <option value="SL">Sick leave (SL)</option>
                        <option value="SCHEDULE_CHANGE">Schedule change</option>
                    </select>
                </label>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="m3-label" x-text="type === 'SCHEDULE_CHANGE' ? 'Date' : 'Start date'"></span>
                        <input type="date" name="start_date" x-model="start" required class="m3-field">
                    </label>
                    <label class="block text-sm" x-show="type !== 'SCHEDULE_CHANGE'">
                        <span class="m3-label">End date</span>
                        <input type="date" name="end_date" x-model="end" x-bind:disabled="type === 'SCHEDULE_CHANGE'" required class="m3-field">
                    </label>
                </div>
                <input type="hidden" name="end_date" x-bind:value="start" x-bind:disabled="type !== 'SCHEDULE_CHANGE'">
                <label class="mt-3 block text-sm" x-show="type === 'SCHEDULE_CHANGE'">
                    <span class="m3-label">Requested status</span>
                    <select name="requested_status" x-bind:disabled="type !== 'SCHEDULE_CHANGE'" class="m3-field">
                        <option value="">Choose</option>
                        <option value="SHIFT" @selected(old('requested_status') === 'SHIFT')>ON · Working</option>
                        <option value="OFF" @selected(old('requested_status') === 'OFF')>OFF · Off</option>
                    </select>
                </label>
                <label class="relative mt-3 block text-sm">
                    <textarea name="reason" rows="3" required placeholder=" " class="peer m3-field">{{ old('reason') }}</textarea>
                    <span class="m3-float">Reason</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-text state-layer" x-on:click="open = false">Cancel</button>
                    <button type="submit" class="btn-filled state-layer">Submit</button>
                </div>
            </form>
        </div>

        @if($currentUser->isManager())
            <div x-show="reviewOpen" x-cloak class="m3-scrim" x-on:click.self="reviewOpen = false" x-on:keydown.escape.window="reviewOpen = false">
                <form method="POST" x-bind:action="actionUrl" class="m3-sheet">
                    <div class="m3-handle"></div>
                    @csrf
                    <h2 class="text-[22px] leading-7" x-text="reviewAction === 'approve' ? 'Approve request' : 'Reject request'"></h2>
                    <label class="mt-5 flex items-center gap-3 text-sm" x-show="reviewAction === 'approve' && reviewType === 'SCHEDULE_CHANGE'" x-cloak>
                        <input type="checkbox" name="credit_dp" value="1" class="m3-check" x-ref="creditDp">
                        <span>Credit +1 day pending</span>
                    </label>
                    <label class="relative mt-3 block text-sm">
                        <textarea name="manager_note" rows="3" x-ref="managerNote" placeholder=" " class="peer m3-field"></textarea>
                        <span class="m3-float">Manager note</span>
                    </label>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-text state-layer" x-on:click="reviewOpen = false">Cancel</button>
                        <button type="submit" class="state-layer" :class="reviewAction === 'approve' ? 'btn-filled' : 'btn-filled bg-error text-on-error'" x-text="reviewAction === 'approve' ? 'Approve' : 'Reject'"></button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
