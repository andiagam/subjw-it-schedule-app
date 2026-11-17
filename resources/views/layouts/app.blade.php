<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Roster') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0..1,0&display=swap" rel="stylesheet">
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const theme = stored === 'light' || stored === 'dark'
                ? stored
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen bg-surface text-on-surface antialiased">
    @php
        $nav = [
            ['route' => 'roster.index', 'match' => 'roster.index', 'label' => 'Roster', 'icon' => 'calendar_month'],
            ['route' => 'requests.index', 'match' => 'requests.*', 'label' => 'Requests', 'icon' => 'inbox'],
        ];
        if ($currentUser?->isManager()) {
            $nav[] = ['route' => 'team.index', 'match' => 'team.*', 'label' => 'Team', 'icon' => 'group'];
        }
    @endphp

    <div>
        <header
            class="{{ $currentUser ? 'sticky top-0 z-30 border-b border-outline-variant bg-surface-lowest' : 'absolute inset-x-0 top-0 z-30' }}"
            x-data="{ lifted: false }"
            x-on:scroll.window="lifted = window.scrollY > 4"
            :class="lifted && 'shadow-sm'"
        >
            <div class="mx-auto flex max-w-[1600px] flex-wrap items-center gap-x-2 gap-y-1 px-6 py-2 md:h-16 md:flex-nowrap md:py-0 lg:gap-3 lg:px-8">
            @if($currentUser)
                <a href="{{ route('roster.index') }}" aria-label="IT Roster" class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-on-primary">
                    <x-icon name="event_available" filled class="text-[20px]" />
                </a>
                <nav class="order-last flex w-full min-w-0 items-center gap-1 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] md:order-none md:w-auto md:flex-1 md:pb-0 [&::-webkit-scrollbar]:hidden" aria-label="Primary">
                    @foreach($nav as $item)
                        @php $active = request()->routeIs($item['match']); @endphp
                        <a href="{{ route($item['route']) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm whitespace-nowrap sm:px-4 sm:py-2 {{ $active ? 'bg-secondary-container font-semibold text-on-secondary-container' : 'font-medium text-on-surface-variant hover:bg-surface-high' }}">
                            <x-icon :name="$item['icon']" :filled="$active" class="text-[18px]" />
                            {{ $item['label'] }}
                            @if($item['route'] === 'requests.index' && $currentUser->isManager() && $pendingRequestCount > 0)
                                <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-medium text-on-error">{{ $pendingRequestCount }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @else
                <div class="min-w-0 flex-1"></div>
            @endif

            <button
                type="button"
                class="ml-auto grid size-10 shrink-0 place-items-center rounded-full border border-outline-variant bg-surface-lowest text-on-surface hover:bg-surface-high md:ml-0"
                role="switch"
                x-data="{ dark: document.documentElement.classList.contains('dark') }"
                :aria-checked="dark.toString()"
                :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
                x-on:click="dark = !dark; document.documentElement.classList.toggle('dark', dark); document.documentElement.dataset.theme = dark ? 'dark' : 'light'; localStorage.setItem('theme', dark ? 'dark' : 'light')"
            >
                <span class="material-symbols-rounded text-[20px]" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
            </button>

            @if($currentUser)
                @php
                    $nameParts = preg_split('/\s+/', trim($currentUser->name)) ?: [];
                    $initials = strtoupper(mb_substr($nameParts[0] ?? 'U', 0, 1).mb_substr($nameParts[1] ?? '', 0, 1));
                @endphp
                @if($canSwitchUser)
                    <div class="relative shrink-0" x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
                        <button type="button" class="inline-flex h-10 max-w-[14rem] items-center gap-2 rounded-full border border-outline-variant bg-surface-lowest py-1 pr-2 pl-1 text-sm hover:bg-surface-high" x-on:click="menu = !menu" :aria-expanded="menu.toString()" aria-label="{{ $currentUser->name }}">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary text-xs font-medium text-on-primary" aria-hidden="true">{{ $initials }}</span>
                            <span class="hidden truncate sm:inline">{{ $currentUser->name }}</span>
                            <x-icon name="expand_more" class="text-[18px] text-on-surface-variant" />
                        </button>
                        <div x-show="menu" x-cloak x-on:click.outside="menu = false" class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-outline-variant bg-surface-lowest py-1 shadow-lg">
                            @foreach($switchUsers as $person)
                                @php
                                    $parts = preg_split('/\s+/', trim($person->name)) ?: [];
                                    $letters = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
                                @endphp
                                <form method="POST" action="{{ route('switch-user') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $person->id }}">
                                    <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-surface-high {{ $currentUser->id === $person->id ? 'text-primary' : '' }}">
                                        <span class="grid size-7 place-items-center rounded-full bg-primary-container text-[11px] font-medium text-on-primary-container">{{ $letters }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium">{{ $person->name }}</span>
                                            <span class="block truncate text-xs text-on-surface-variant">{{ $person->job_title }}</span>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @else
                    <span class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border border-outline-variant bg-surface-lowest py-1 pr-3 pl-1 text-sm">
                        <span class="grid size-8 place-items-center rounded-full bg-primary text-xs font-medium text-on-primary" aria-hidden="true">{{ $initials }}</span>
                        <span class="hidden truncate sm:inline">{{ $currentUser->name }}</span>
                    </span>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="grid size-10 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high" aria-label="Log out" title="Log out">
                        <x-icon name="logout" class="text-[20px]" />
                    </button>
                </form>
            @endif
            </div>
        </header>

        @if($currentUser && $impersonator)
            <div class="bg-warning-container px-4 py-2 text-center text-sm text-on-warning-container">
                Viewing as {{ $currentUser->name }}. Use Quick switch to return to {{ $impersonator->name }}.
            </div>
        @endif

        <main class="{{ $currentUser ? 'px-6 py-6 pb-24 lg:px-8' : 'min-h-screen flex items-center justify-center p-4' }}">
            <div class="{{ $currentUser ? 'mx-auto w-full max-w-[1600px]' : 'w-full max-w-md' }}">
                @if($errors->any())
                    <div class="mb-4 rounded-xl bg-error-container px-4 py-3 text-sm text-on-error-container">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

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
        x-transition
        class="fixed inset-x-4 bottom-6 z-50 md:left-auto md:right-6 md:w-[22rem]"
    >
        <div class="flex items-start gap-3 rounded-xl bg-inverse-surface px-4 py-3 text-sm text-inverse-on-surface shadow-lg">
            <x-icon name="info" class="text-[20px]" />
            <p class="flex-1" x-text="message"></p>
            <button type="button" class="text-sm font-medium" x-on:click="show = false">Close</button>
        </div>
    </div>

</body>
</html>
