<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Roster') · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-[1600px] items-center gap-2 px-3 sm:gap-3 sm:px-4">
            <a href="{{ route('roster.index') }}" class="shrink-0 text-sm font-semibold tracking-tight text-ink">IT Roster</a>

            @if($currentUser)
                <nav class="hidden items-center gap-1 text-sm md:flex">
                    <a href="{{ route('roster.index') }}" class="inline-flex h-9 items-center rounded-lg px-3 {{ request()->routeIs('roster.index') ? 'bg-button text-button-ink' : 'text-muted hover:bg-surface-2' }}">Roster</a>
                    <a href="{{ route('requests.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg px-3 {{ request()->routeIs('requests.*') ? 'bg-button text-button-ink' : 'text-muted hover:bg-surface-2' }}">
                        Requests
                        @if($currentUser->isManager())
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold {{ $pendingRequestCount > 0 ? 'bg-rose-600 text-white' : (request()->routeIs('requests.*') ? 'bg-button-ink/15 text-button-ink' : 'bg-surface-2 text-muted') }}">{{ $pendingRequestCount }}</span>
                        @endif
                    </a>
                    @if($currentUser->isManager())
                        <a href="{{ route('team.index') }}" class="inline-flex h-9 items-center rounded-lg px-3 {{ request()->routeIs('team.*') ? 'bg-button text-button-ink' : 'text-muted hover:bg-surface-2' }}">Team</a>
                    @endif
                </nav>

                <div class="ml-auto flex min-w-0 items-center gap-2">
                    @if($canSwitchUser)
                        <form method="POST" action="{{ route('switch-user') }}" class="min-w-0">
                            @csrf
                            <label for="switch-user" class="sr-only">Quick switch</label>
                            <select id="switch-user" name="user_id" onchange="this.form.submit()" class="h-9 max-w-[11rem] truncate rounded-lg border border-line bg-field px-2 text-xs text-ink sm:max-w-xs">
                                @foreach($switchUsers as $person)
                                    <option value="{{ $person->id }}" @selected($currentUser->id === $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <span class="truncate text-sm font-medium">{{ $currentUser->name }}</span>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-line px-3 text-xs font-medium text-muted hover:bg-surface-2">Log out</button>
                    </form>
                </div>
            @endif
        </div>
    </header>

    @if($currentUser && $impersonator)
        <div class="border-b border-amber-300/40 bg-amber-200/15 px-4 py-2 text-center text-sm text-amber-100">
            Viewing as {{ $currentUser->name }}. Use Quick switch to return to {{ $impersonator->name }}.
        </div>
    @endif

    <main class="mx-auto max-w-[1600px] px-3 py-4 pb-24 sm:px-4 md:py-6 md:pb-6">
        @if($errors->any())
            <div class="mb-4 rounded-xl border border-rose-300/40 bg-rose-200/15 px-4 py-3 text-sm text-rose-100">
                {{ $errors->first() }}
            </div>
        @endif

        @yield('content')
    </main>

    <div
        x-data="{
            show: {{ session('status') || session('error') ? 'true' : 'false' }},
            message: @js(session('status') ?? session('error') ?? ''),
            kind: @js(session('error') ? 'error' : 'success'),
            timer: null,
            notify(detail) {
                this.message = detail.message;
                this.kind = detail.kind || 'success';
                this.show = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.show = false, 4000);
            }
        }"
        x-on:toast.window="notify($event.detail)"
        x-init="if (show) { timer = setTimeout(() => show = false, 4000) }"
        x-show="show"
        x-cloak
        class="fixed inset-x-3 bottom-20 z-50 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:max-w-sm md:bottom-4"
    >
        <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg" :class="kind === 'error' ? 'border-rose-300/40 bg-rose-200/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-200/15 text-emerald-100'">
            <p class="flex-1" x-text="message"></p>
            <button type="button" class="text-xs font-semibold uppercase tracking-wide opacity-70" x-on:click="show = false">Close</button>
        </div>
    </div>

    @if($currentUser)
        <nav class="fixed inset-x-0 bottom-0 z-40 flex border-t border-line bg-surface md:hidden">
            <a href="{{ route('roster.index') }}" class="flex min-h-14 flex-1 items-center justify-center text-sm font-semibold {{ request()->routeIs('roster.index') ? 'text-ink' : 'text-faint' }}">Roster</a>
            <a href="{{ route('requests.index') }}" class="flex min-h-14 flex-1 items-center justify-center gap-1 text-sm font-semibold {{ request()->routeIs('requests.*') ? 'text-ink' : 'text-faint' }}">
                Requests
                @if($currentUser->isManager() && $pendingRequestCount > 0)
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] text-white">{{ $pendingRequestCount }}</span>
                @endif
            </a>
            @if($currentUser->isManager())
                <a href="{{ route('team.index') }}" class="flex min-h-14 flex-1 items-center justify-center text-sm font-semibold {{ request()->routeIs('team.*') ? 'text-ink' : 'text-faint' }}">Team</a>
            @endif
        </nav>
    @endif
</body>
</html>
