@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-line bg-surface p-6 shadow-sm">
            <h1 class="text-xl font-semibold tracking-tight">Sign in</h1>
            <p class="mt-1 text-sm text-faint">Use your username and 4-digit PIN.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-muted">Username</span>
                    <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="min-h-11 w-full rounded-lg border border-line bg-field px-3 py-2 shadow-sm focus:border-faint focus:outline-none focus:ring-2 focus:ring-line">
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-muted">PIN</span>
                    <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required autocomplete="current-password" class="min-h-11 w-full rounded-lg border border-line bg-field px-3 py-2 tracking-[0.4em] shadow-sm focus:border-faint focus:outline-none focus:ring-2 focus:ring-line">
                </label>
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-button px-4 text-sm font-semibold text-button-ink hover:bg-white">Sign in</button>
            </form>
        </div>
    </div>
@endsection
