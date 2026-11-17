@extends('layouts.app')

@section('title', 'Team')

@section('content')
    <div
        x-data="{
            open: {{ $errors->any() && old('form') === 'team' ? 'true' : 'false' }},
            editing: {{ old('_method') === 'PUT' ? 'true' : 'false' }},
            action: @js(old('_method') === 'PUT' && old('user_id') ? url('/team/'.old('user_id')) : route('team.store')),
            name: @js(old('name', '')),
            username: @js(old('username', '')),
            job_title: @js(old('job_title', '')),
            role: @js(old('role', 'IT Staff')),
            pin: @js(old('pin', '')),
            start_date: @js(old('start_date', '')),
            end_date: @js(old('end_date', '')),
            al_balance: @js(old('al_balance', 12)),
            dp_balance: @js(old('dp_balance', 0)),
            user_id: @js(old('user_id', '')),
            filter: 'ALL',
            resetOpen: {{ $errors->any() && old('form') === 'reset-pin' ? 'true' : 'false' }},
            resetName: @js(old('reset_name', '')),
            resetAction: @js(old('user_id') ? url('/team/'.old('user_id').'/pin') : ''),
            openCreate() {
                this.editing = false;
                this.action = @js(route('team.store'));
                this.name = '';
                this.username = '';
                this.job_title = '';
                this.role = 'IT Staff';
                this.pin = '';
                this.start_date = '';
                this.end_date = '';
                this.al_balance = 12;
                this.dp_balance = 0;
                this.user_id = '';
                this.open = true;
            },
            openEdit(member) {
                this.editing = true;
                this.action = @js(url('/team')).replace(/\/$/, '') + '/' + member.id;
                this.name = member.name;
                this.username = member.username;
                this.job_title = member.job_title;
                this.role = member.role;
                this.pin = '';
                this.start_date = member.start_date || '';
                this.end_date = member.end_date || '';
                this.al_balance = member.al_balance;
                this.dp_balance = member.dp_balance;
                this.user_id = member.id;
                this.open = true;
            },
            openReset(member) {
                this.resetName = member.name;
                this.resetAction = @js(url('/team')).replace(/\/$/, '') + '/' + member.id + '/pin';
                this.resetOpen = true;
            }
        }"
    >
        <div class="mb-4">
            <p class="text-sm text-on-surface-variant">Active employees, roles, and leave balances.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach(['ALL' => 'All', 'IT Manager' => 'IT Manager', 'IT Staff' => 'IT Staff', 'IT Trainee' => 'IT Trainee'] as $value => $label)
                    <button type="button" class="m3-chip state-layer" :class="filter === @js($value) && 'm3-chip-selected'" x-on:click="filter = @js($value)">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <section class="m3-card">
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-[720px] text-sm">
                    <thead class="text-left text-xs font-medium tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Username</th>
                            <th class="px-3 py-2">Job title</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2">AL</th>
                            <th class="px-3 py-2">DP</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            @php
                                $payload = [
                                    'id' => $member->id,
                                    'name' => $member->name,
                                    'username' => $member->username,
                                    'job_title' => $member->job_title,
                                    'role' => $member->role,
                                    'start_date' => $member->start_date?->toDateString(),
                                    'end_date' => $member->end_date?->toDateString(),
                                    'al_balance' => $member->al_balance,
                                    'dp_balance' => $member->dp_balance,
                                ];
                            @endphp
                            <tr class="row-hover" x-show="filter === 'ALL' || filter === @js($member->role)">
                                <td class="px-3 py-3 font-medium">
                                    {{ $member->name }}
                                    @if($member->is_locked)
                                        <span class="ml-2 rounded-full bg-error-container px-2 py-0.5 text-[11px] font-medium text-on-error-container">Locked</span>
                                    @endif
                                    @if($member->employmentHasEnded())
                                        <span class="ml-2 rounded-full bg-neutral-950 px-2 py-0.5 text-[11px] font-medium text-white">Ended</span>
                                    @endif
                                    <div class="mt-0.5 text-xs font-normal text-on-surface-variant">Start {{ $member->start_date?->format('d M Y') ?? '—' }} · End {{ $member->end_date?->format('d M Y') ?? '—' }}</div>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $member->username }}</td>
                                <td class="px-3 py-3">{{ $member->job_title }}</td>
                                <td class="px-3 py-3"><x-status-pill :status="$member->role" /></td>
                                <td class="px-3 py-3">{{ $member->al_balance }}</td>
                                <td class="px-3 py-3">{{ $member->dp_balance }}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    @if($member->is_locked)
                                        <form method="POST" action="{{ route('team.unlock', $member) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-text state-layer h-8 text-xs">Unlock</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn-text state-layer h-8 text-xs" x-on:click="openReset(@js($payload))">Reset PIN</button>
                                    <button type="button" class="btn-outlined state-layer h-8 px-4 text-xs" x-on:click="openEdit(@js($payload))">Edit</button>
                                    <form method="POST" action="{{ route('team.archive', $member) }}" class="inline" onsubmit="return confirm(@js('Archive '.$member->name.'? They will leave the roster and will not be able to sign in.'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-text state-layer h-8 text-xs text-error">Archive</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="space-y-3 md:hidden">
                @foreach($members as $member)
                    @php
                        $payload = [
                            'id' => $member->id,
                            'name' => $member->name,
                            'username' => $member->username,
                            'job_title' => $member->job_title,
                            'role' => $member->role,
                            'start_date' => $member->start_date?->toDateString(),
                            'end_date' => $member->end_date?->toDateString(),
                            'al_balance' => $member->al_balance,
                            'dp_balance' => $member->dp_balance,
                        ];
                    @endphp
                    <article class="rounded-2xl bg-surface-lowest p-4" x-show="filter === 'ALL' || filter === @js($member->role)">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ $member->name }}
                                    @if($member->is_locked)
                                        <span class="ml-2 rounded-full bg-error-container px-2 py-0.5 text-[11px] font-medium text-on-error-container">Locked</span>
                                    @endif
                                    @if($member->employmentHasEnded())
                                        <span class="ml-2 rounded-full bg-neutral-950 px-2 py-0.5 text-[11px] font-medium text-white">Ended</span>
                                    @endif
                                </div>
                                <div class="text-sm text-on-surface-variant">{{ $member->job_title }}</div>
                                <div class="mt-1 text-xs text-on-surface-variant">Start {{ $member->start_date?->format('d M Y') ?? '—' }} · End {{ $member->end_date?->format('d M Y') ?? '—' }}</div>
                                <div class="mt-1 font-mono text-xs">{{ $member->username }}</div>
                            </div>
                            <x-status-pill :status="$member->role" />
                        </div>
                        <p class="mt-3 text-sm">{{ $member->al_balance }} annual · {{ $member->dp_balance }} day pending</p>
                        <div class="mt-3 flex flex-wrap justify-end gap-1">
                            @if($member->is_locked)
                                <form method="POST" action="{{ route('team.unlock', $member) }}">
                                    @csrf
                                    <button type="submit" class="btn-text state-layer">Unlock</button>
                                </form>
                            @endif
                            <button type="button" class="btn-text state-layer" x-on:click="openReset(@js($payload))">Reset PIN</button>
                            <form method="POST" action="{{ route('team.archive', $member) }}" onsubmit="return confirm(@js('Archive '.$member->name.'? They will leave the roster and will not be able to sign in.'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-text state-layer text-error">Archive</button>
                            </form>
                            <button type="button" class="btn-outlined state-layer" x-on:click="openEdit(@js($payload))">Edit</button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <button type="button" class="btn-fab state-layer" x-on:click="openCreate()">
            <x-icon name="person_add" />
            Add employee
        </button>

        <div x-show="open" x-cloak class="m3-scrim" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <form method="POST" x-bind:action="action" class="m3-sheet sm:max-w-lg">
                <div class="m3-handle"></div>
                @csrf
                <input type="hidden" name="form" value="team">
                <input type="hidden" name="user_id" x-model="user_id">
                <template x-if="editing">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <h2 class="text-[22px] leading-7" x-text="editing ? 'Edit employee' : 'Add employee'"></h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="relative block text-sm sm:col-span-2">
                        <input type="text" name="name" x-model="name" required placeholder=" " class="peer m3-field">
                        <span class="m3-float">Name</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="text" name="username" x-model="username" required placeholder=" " class="peer m3-field">
                        <span class="m3-float">Username</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="text" name="job_title" x-model="job_title" required placeholder=" " class="peer m3-field">
                        <span class="m3-float">Job title</span>
                    </label>
                    <label class="block text-sm">
                        <span class="m3-label">Role</span>
                        <select name="role" x-model="role" class="m3-field">
                            @foreach(\App\Enums\Role::cases() as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->value }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="relative block text-sm">
                        <input type="password" name="pin" x-model="pin" inputmode="numeric" minlength="6" maxlength="32" pattern="[0-9]{6,}" x-bind:required="!editing" placeholder=" " autocomplete="new-password" class="peer m3-field tracking-[0.3em]">
                        <span class="m3-float" x-text="editing ? 'New PIN (optional)' : 'PIN (min. 6 digits)'"></span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="date" name="start_date" x-model="start_date" placeholder=" " class="peer m3-field">
                        <span class="m3-float">Start date</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="date" name="end_date" x-model="end_date" placeholder=" " class="peer m3-field">
                        <span class="m3-float">End date</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="number" name="al_balance" x-model="al_balance" min="0" max="999" required placeholder=" " class="peer m3-field">
                        <span class="m3-float">Annual leave</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="number" name="dp_balance" x-model="dp_balance" min="0" max="999" required placeholder=" " class="peer m3-field">
                        <span class="m3-float">Day pending</span>
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-text state-layer" x-on:click="open = false">Cancel</button>
                    <button type="submit" class="btn-filled state-layer" x-text="editing ? 'Save changes' : 'Add employee'"></button>
                </div>
            </form>
        </div>

        <div x-show="resetOpen" x-cloak class="m3-scrim" x-on:click.self="resetOpen = false" x-on:keydown.escape.window="resetOpen = false">
            <form method="POST" x-bind:action="resetAction" class="m3-sheet sm:max-w-sm">
                <div class="m3-handle"></div>
                @csrf
                <input type="hidden" name="form" value="reset-pin">
                <input type="hidden" name="reset_name" x-bind:value="resetName">
                <h2 class="text-[22px] leading-7">Reset PIN</h2>
                <p class="mt-1 text-sm text-on-surface-variant">Set a new PIN of at least 6 digits for <span x-text="resetName"></span>. This also unlocks the account.</p>
                <div class="mt-5 space-y-3">
                    <label class="relative block text-sm">
                        <input type="password" name="pin" inputmode="numeric" minlength="6" pattern="[0-9]{6,}" required placeholder=" " autocomplete="new-password" class="peer m3-field tracking-[0.3em]">
                        <span class="m3-float">New PIN (min. 6 digits)</span>
                    </label>
                    <label class="relative block text-sm">
                        <input type="password" name="pin_confirmation" inputmode="numeric" minlength="6" pattern="[0-9]{6,}" required placeholder=" " autocomplete="new-password" class="peer m3-field tracking-[0.3em]">
                        <span class="m3-float">Confirm New PIN</span>
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-text state-layer" x-on:click="resetOpen = false">Cancel</button>
                    <button type="submit" class="btn-filled state-layer">Reset PIN</button>
                </div>
            </form>
        </div>
    </div>
@endsection
