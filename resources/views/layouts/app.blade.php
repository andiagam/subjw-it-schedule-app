<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appName = trim((string) config('app.name'));
        if ($appName === '' || strcasecmp($appName, 'Laravel') === 0) {
            $appName = 'SUBJW IT Schedule';
        }
    @endphp
    <title>@yield('title', 'Roster') · {{ $appName }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
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
    <div id="nav-progress" class="nav-progress hidden" aria-hidden="true"><span></span></div>
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
            class="{{ $currentUser ? 'sticky top-0 z-50 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-[#1E1F23]' : 'absolute inset-x-0 top-0 z-30' }}"
            x-data="{ lifted: false }"
            x-on:scroll.window="lifted = window.scrollY > 4"
            :class="lifted && 'shadow-sm'"
        >
            <div class="mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-1 px-3 sm:h-16 sm:gap-3 sm:px-6 lg:px-8">
            @if($currentUser)
                <div class="flex min-w-0 flex-1 items-center gap-1 sm:gap-2">
                    <a href="{{ route('roster.index') }}" aria-label="IT Roster" class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-on-primary sm:size-9 sm:rounded-xl">
                        <x-icon name="event_available" filled class="text-[18px] sm:text-[20px]" />
                    </a>
                    <nav class="flex min-w-0 items-center gap-1 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-1 [&::-webkit-scrollbar]:hidden" aria-label="Primary">
                        @foreach($nav as $item)
                            @php $active = request()->routeIs($item['match']); @endphp
                            <a href="{{ route($item['route']) }}" data-nav @if($active) aria-current="page" @endif class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1.5 text-xs whitespace-nowrap min-[400px]:px-2.5 sm:gap-1.5 sm:px-4 sm:py-2 sm:text-sm {{ $active ? 'bg-secondary-container font-semibold text-on-secondary-container' : 'font-medium text-on-surface-variant hover:bg-surface-high' }}">
                                <x-icon :name="$item['icon']" :filled="$active" class="!hidden text-[16px] min-[460px]:!inline-block sm:text-[18px]" />
                                {{ $item['label'] }}
                                @if($item['route'] === 'requests.index' && $currentUser->isManager() && $pendingRequestCount > 0)
                                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-medium text-on-error">{{ $pendingRequestCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </div>
            @else
                <div class="min-w-0 flex-1"></div>
            @endif

            <div class="flex shrink-0 items-center sm:gap-2">
            <button
                type="button"
                class="grid size-8 shrink-0 place-items-center rounded-full border border-outline-variant bg-surface-lowest text-on-surface hover:bg-surface-high sm:size-10"
                role="switch"
                x-data="{ dark: document.documentElement.classList.contains('dark') }"
                :aria-checked="dark.toString()"
                :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
                x-on:click="dark = !dark; document.documentElement.classList.toggle('dark', dark); document.documentElement.dataset.theme = dark ? 'dark' : 'light'; localStorage.setItem('theme', dark ? 'dark' : 'light')"
            >
                <span class="material-symbols-rounded text-[18px] sm:text-[20px]" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
            </button>

            @if($currentUser)
                @php
                    $nameParts = preg_split('/\s+/', trim($currentUser->name)) ?: [];
                    $initials = strtoupper(mb_substr($nameParts[0] ?? 'U', 0, 1).mb_substr($nameParts[1] ?? '', 0, 1));
                @endphp
                <div class="relative shrink-0" x-data="{ menu: false, pinOpen: {{ $errors->any() && old('form') === 'pin' ? 'true' : 'false' }} }" x-on:keydown.escape.window="menu = false; if (!pinOpen) return; pinOpen = false">
                    <button type="button" class="inline-flex h-8 items-center gap-0 rounded-full border border-outline-variant bg-surface-lowest py-0.5 pr-0 pl-0.5 text-sm hover:bg-surface-high sm:h-10 sm:max-w-[14rem] sm:gap-2 sm:py-1 sm:pr-2 sm:pl-1" x-on:click="menu = !menu" :aria-expanded="menu.toString()" aria-label="{{ $currentUser->name }}">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-[10px] font-medium text-on-primary sm:size-8 sm:text-xs" aria-hidden="true">{{ $initials }}</span>
                        <span class="hidden truncate sm:inline">{{ $currentUser->name }}</span>
                        <x-icon name="expand_more" class="text-[16px] text-on-surface-variant sm:text-[18px]" />
                    </button>
                    <div x-show="menu" x-cloak x-on:click.outside="menu = false" class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-outline-variant bg-surface-lowest py-1 shadow-lg">
                        @if($canSwitchUser)
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
                        @endif
                        <button type="button" class="flex w-full items-center gap-2 border-t border-outline-variant px-3 py-2 text-left text-sm hover:bg-surface-high" x-on:click="menu = false; pinOpen = true">
                            <x-icon name="key" class="text-[18px] text-on-surface-variant" />
                            Change PIN
                        </button>
                    </div>
                    <div x-show="pinOpen" x-cloak class="m3-scrim" x-on:click.self="pinOpen = false">
                        <form method="POST" action="{{ route('account.pin') }}" class="m3-sheet sm:max-w-sm">
                            <div class="m3-handle"></div>
                            @csrf
                            <input type="hidden" name="form" value="pin">
                            <h2 class="text-[22px] leading-7">Change PIN</h2>
                            <p class="mt-1 text-sm text-on-surface-variant">Use at least 6 digits.</p>
                            <div class="mt-5 space-y-3">
                                <label class="relative block text-sm">
                                    <input type="password" name="current_pin" inputmode="numeric" pattern="[0-9]{4,}" required placeholder=" " autocomplete="current-password" class="peer m3-field tracking-[0.3em]">
                                    <span class="m3-float">Current PIN</span>
                                </label>
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
                                <button type="button" class="btn-text state-layer" x-on:click="pinOpen = false">Cancel</button>
                                <button type="submit" class="btn-filled state-layer">Save PIN</button>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="grid size-8 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high sm:size-10" aria-label="Log out" title="Log out">
                        <x-icon name="logout" class="text-[18px] sm:text-[20px]" />
                    </button>
                </form>
            @endif
            </div>
            </div>
        </header>

        @if($currentUser && $impersonator)
            <div class="bg-warning-container px-4 py-2 text-center text-sm text-on-warning-container">
                Viewing as {{ $currentUser->name }}. Use Quick switch to return to {{ $impersonator->name }}.
            </div>
        @endif

        <main class="{{ $currentUser ? 'px-4 pt-3 pb-24 sm:px-6 sm:pt-6 lg:px-8' : 'min-h-screen flex items-center justify-center p-4' }}">
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
