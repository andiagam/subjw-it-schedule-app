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
            role: @js(old('role', 'STAFF')),
            pin: @js(old('pin', '')),
            al_balance: @js(old('al_balance', 12)),
            dp_balance: @js(old('dp_balance', 0)),
            user_id: @js(old('user_id', '')),
            openCreate() {
                this.editing = false;
                this.action = @js(route('team.store'));
                this.name = '';
                this.username = '';
                this.job_title = '';
                this.role = 'STAFF';
                this.pin = '';
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
                this.pin = member.pin;
                this.al_balance = member.al_balance;
                this.dp_balance = member.dp_balance;
                this.user_id = member.id;
                this.open = true;
            }
        }"
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Team</h1>
                <p class="text-sm text-faint">Active employees, roles, and leave balances.</p>
            </div>
            <button type="button" class="inline-flex min-h-11 items-center rounded-lg bg-button px-4 text-sm font-semibold text-button-ink hover:bg-white" x-on:click="openCreate()">Add employee</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-line bg-surface shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[720px] text-sm">
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-faint">
                        <tr>
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Username</th>
                            <th class="px-4 py-2 font-medium">Job title</th>
                            <th class="px-4 py-2 font-medium">Role</th>
                            <th class="px-4 py-2 font-medium">AL</th>
                            <th class="px-4 py-2 font-medium">DP</th>
                            <th class="px-4 py-2 font-medium"></th>
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
                                    'pin' => $member->pin,
                                    'al_balance' => $member->al_balance,
                                    'dp_balance' => $member->dp_balance,
                                ];
                            @endphp
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $member->username }}</td>
                                <td class="px-4 py-3">{{ $member->job_title }}</td>
                                <td class="px-4 py-3"><x-status-pill :status="$member->role" /></td>
                                <td class="px-4 py-3">{{ $member->al_balance }}</td>
                                <td class="px-4 py-3">{{ $member->dp_balance }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold" x-on:click="openEdit(@js($payload))">Edit</button>
                                    <form method="POST" action="{{ route('team.archive', $member) }}" class="inline" onsubmit="return confirm(@js('Archive '.$member->name.'? They will leave the roster and will not be able to sign in.'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-200 hover:bg-rose-200/15">Archive</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-3 sm:items-center sm:p-4" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
            <form method="POST" x-bind:action="action" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-surface p-5 shadow-xl">
                @csrf
                <input type="hidden" name="form" value="team">
                <input type="hidden" name="user_id" x-model="user_id">
                <template x-if="editing">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <h2 class="text-lg font-semibold" x-text="editing ? 'Edit employee' : 'Add employee'"></h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block font-medium">Name</span>
                        <input type="text" name="name" x-model="name" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Username</span>
                        <input type="text" name="username" x-model="username" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Job title</span>
                        <input type="text" name="job_title" x-model="job_title" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Role</span>
                        <select name="role" x-model="role" class="w-full rounded-lg border border-line px-3 py-2">
                            <option value="STAFF">Staff</option>
                            <option value="MANAGER">Manager</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">PIN</span>
                        <input type="text" name="pin" x-model="pin" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required class="w-full rounded-lg border border-line px-3 py-2 tracking-[0.3em]">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Annual leave balance</span>
                        <input type="number" name="al_balance" x-model="al_balance" min="0" max="999" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Day pending balance</span>
                        <input type="number" name="dp_balance" x-model="dp_balance" min="0" max="999" required class="w-full rounded-lg border border-line px-3 py-2">
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg px-3 py-2 text-sm text-muted hover:bg-surface-2" x-on:click="open = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-button px-3 py-2 text-sm font-semibold text-button-ink" x-text="editing ? 'Save changes' : 'Add employee'"></button>
                </div>
            </form>
        </div>
    </div>
@endsection
