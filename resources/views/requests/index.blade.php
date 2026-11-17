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
    @endphp

    <div
        x-data="{
            open: {{ $errors->any() && old('form') === 'leave' ? 'true' : 'false' }},
            type: @js(old('type', 'AL')),
            start: @js(old('start_date', '')),
            end: @js(old('end_date', '')),
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
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Leave and schedule changes</h1>
                <p class="text-sm text-faint">
                    Annual leave left: <span class="font-semibold text-ink">{{ $currentUser->al_balance }}</span>
                    · Day pending left: <span class="font-semibold text-ink">{{ $currentUser->dp_balance }}</span>
                </p>
            </div>
            <button type="button" class="inline-flex min-h-11 items-center rounded-lg bg-button px-4 text-sm font-semibold text-button-ink hover:bg-white" x-on:click="open = true">New request</button>
        </div>

        @if($currentUser->isManager())
            <section class="mb-6 overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">
                <div class="border-b border-line px-4 py-3">
                    <h2 class="font-semibold">Approval inbox</h2>
                    <p class="text-sm text-faint">{{ $pending->count() }} pending</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[720px] text-sm">
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-faint">
                            <tr>
                                <th class="px-4 py-2 font-medium">Employee</th>
                                <th class="px-4 py-2 font-medium">Type</th>
                                <th class="px-4 py-2 font-medium">Dates</th>
                                <th class="px-4 py-2 font-medium">Reason</th>
                                <th class="px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending as $item)
                                <tr class="border-t border-line">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $item->user->name }}</div>
                                        <div class="text-xs text-faint">{{ $item->user->job_title }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-pill :status="$item->type" />
                                        @if($item->type === 'SCHEDULE_CHANGE')
                                            <div class="mt-1 text-xs text-faint">Change to {{ $item->requested_status }}</div>
                                        @else
                                            <div class="mt-1 text-xs text-faint">{{ $item->dayCount() }} {{ $item->dayCount() === 1 ? 'day' : 'days' }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}
                                    </td>
                                    <td class="max-w-xs px-4 py-3 text-muted">{{ $item->reason }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white" x-on:click="openReview({{ $item->id }}, @js($item->type), 'approve')">Approve</button>
                                        <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-muted" x-on:click="openReview({{ $item->id }}, @js($item->type), 'reject')">Reject</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-faint">No requests waiting for review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">
            <div class="border-b border-line px-4 py-3">
                <h2 class="font-semibold">{{ $currentUser->isManager() ? 'All requests' : 'My requests' }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[720px] text-sm">
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-faint">
                        <tr>
                            @if($currentUser->isManager())
                                <th class="px-4 py-2 font-medium">Employee</th>
                            @endif
                            <th class="px-4 py-2 font-medium">Type</th>
                            <th class="px-4 py-2 font-medium">Dates</th>
                            <th class="px-4 py-2 font-medium">Reason</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Manager note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $item)
                            <tr class="border-t border-line align-top">
                                @if($currentUser->isManager())
                                    <td class="px-4 py-3 font-medium">{{ $item->user->name }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    {{ $typeLabels[$item->type] ?? $item->type }}
                                    @if($item->type === 'SCHEDULE_CHANGE')
                                        <div class="text-xs text-faint">to {{ $item->requested_status }}@if($item->credit_dp) · +1 day pending @endif</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $item->start_date->equalTo($item->end_date) ? $item->start_date->format('d M Y') : $item->start_date->format('d M Y').' – '.$item->end_date->format('d M Y') }}
                                </td>
                                <td class="max-w-xs px-4 py-3 text-muted">{{ $item->reason }}</td>
                                <td class="px-4 py-3"><x-status-pill :status="$item->status" /></td>
                                <td class="max-w-xs px-4 py-3 text-muted">{{ $item->manager_note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $currentUser->isManager() ? 6 : 5 }}" class="px-4 py-8 text-center text-faint">No requests yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center sm:p-4" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <form method="POST" action="{{ route('requests.store') }}" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-surface p-5 shadow-xl">
                @csrf
                <input type="hidden" name="form" value="leave">
                <h2 class="text-lg font-semibold">New request</h2>
                <label class="mt-4 block text-sm">
                    <span class="mb-1 block font-medium">Type</span>
                    <select name="type" x-model="type" class="w-full rounded-lg border border-line px-3 py-2">
                        <option value="AL">Annual leave (AL)</option>
                        <option value="DP">Day pending (DP)</option>
                        <option value="SL">Sick leave (SL)</option>
                        <option value="SCHEDULE_CHANGE">Schedule change</option>
                    </select>
                </label>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium" x-text="type === 'SCHEDULE_CHANGE' ? 'Date' : 'Start date'"></span>
                        <input type="date" name="start_date" x-model="start" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                    <label class="block text-sm" x-show="type !== 'SCHEDULE_CHANGE'">
                        <span class="mb-1 block font-medium">End date</span>
                        <input type="date" name="end_date" x-model="end" x-bind:disabled="type === 'SCHEDULE_CHANGE'" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                </div>
                <input type="hidden" name="end_date" x-bind:value="start" x-bind:disabled="type !== 'SCHEDULE_CHANGE'">
                <label class="mt-3 block text-sm" x-show="type === 'SCHEDULE_CHANGE'">
                    <span class="mb-1 block font-medium">Requested status</span>
                    <select name="requested_status" x-bind:disabled="type !== 'SCHEDULE_CHANGE'" class="w-full rounded-lg border border-line px-3 py-2">
                        <option value="">Choose</option>
                        <option value="SHIFT" @selected(old('requested_status') === 'SHIFT')>ON · Working</option>
                        <option value="OFF" @selected(old('requested_status') === 'OFF')>OFF · Off</option>
                    </select>
                </label>
                <label class="mt-3 block text-sm">
                    <span class="mb-1 block font-medium">Reason</span>
                    <textarea name="reason" rows="3" required class="w-full rounded-lg border border-line px-3 py-2">{{ old('reason') }}</textarea>
                </label>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-2" x-on:click="open = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-button px-3 py-2 text-sm font-semibold text-button-ink">Submit</button>
                </div>
            </form>
        </div>

        @if($currentUser->isManager())
            <div x-show="reviewOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center sm:p-4" x-on:click.self="reviewOpen = false" x-on:keydown.escape.window="reviewOpen = false">
                <form method="POST" x-bind:action="actionUrl" class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-surface p-5 shadow-xl">
                    @csrf
                    <h2 class="text-lg font-semibold" x-text="reviewAction === 'approve' ? 'Approve request' : 'Reject request'"></h2>
                    <label class="mt-4 flex items-start gap-2 text-sm" x-show="reviewAction === 'approve' && reviewType === 'SCHEDULE_CHANGE'" x-cloak>
                        <input type="checkbox" name="credit_dp" value="1" class="mt-1" x-ref="creditDp">
                        <span>Credit +1 day pending</span>
                    </label>
                    <label class="mt-3 block text-sm">
                        <span class="mb-1 block font-medium">Manager note</span>
                        <textarea name="manager_note" rows="3" x-ref="managerNote" class="w-full rounded-lg border border-line px-3 py-2" placeholder="Optional"></textarea>
                    </label>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-2" x-on:click="reviewOpen = false">Cancel</button>
                        <button type="submit" class="rounded-lg px-3 py-2 text-sm font-semibold text-white" :class="reviewAction === 'approve' ? 'bg-emerald-700' : 'bg-rose-700'" x-text="reviewAction === 'approve' ? 'Approve' : 'Reject'"></button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endsection
