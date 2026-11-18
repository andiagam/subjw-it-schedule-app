@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="m3-card w-full" style="--field-notch: var(--md-surface-container-low)">
        <div class="mb-6 grid size-14 place-items-center rounded-2xl bg-primary-container text-on-primary-container">
            <x-icon name="event_available" filled />
        </div>
        <h1 class="text-[28px] leading-9">Sign in</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Use your username and PIN (min. 6 digits).</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <x-field label="Username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" />
            <x-field label="PIN" type="password" name="pin" inputmode="numeric" minlength="4" maxlength="32" pattern="[0-9]{4,}" required autocomplete="current-password" class="tracking-[0.3em]" />
            <button type="submit" class="btn-filled state-layer mt-2 w-full">Sign in</button>
        </form>
    </div>
@endsection
