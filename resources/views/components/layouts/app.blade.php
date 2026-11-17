@props(['title' => null, 'width' => 'max-w-5xl'])
@php
    $user = auth()->user();
    $links = [
        ['route' => 'dashboard', 'label' => __('app.nav.dashboard'), 'active' => request()->routeIs('dashboard')],
        ['route' => 'events.index', 'label' => __('app.nav.events'), 'active' => request()->routeIs('events.*')],
        ...($user->account_id ? [['route' => 'frames.index', 'label' => __('frames.nav'), 'active' => request()->routeIs('frames.*')]] : []),
    ];
    if ($user->isAdmin()) {
        $links[] = ['route' => 'admin.accounts.index', 'label' => __('app.nav.admin'), 'active' => false];
    }
@endphp
<x-layouts.base :title="$title">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-bold">{{ __('app.nav.skip') }}</a>

    <header class="sticky top-0 z-30 border-b border-white/10 bg-ink text-white" x-data="navMenu" x-on:keydown.escape.window="close"
            data-open-label="{{ __('app.nav.open_menu') }}" data-close-label="{{ __('app.nav.close_menu') }}">
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4">
            <a href="{{ route('dashboard') }}" class="flex min-h-touch items-center gap-2.5 rounded-lg focus-visible:ring-offset-ink">
                <x-logo />
                <span class="font-display text-xl tracking-wide">{{ __('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="{{ __('app.nav.menu') }}">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" @if ($link['active']) aria-current="page" @endif
                       @class(['rounded-lg px-3 py-2 text-sm font-bold focus-visible:ring-offset-ink', 'bg-white/15 text-white' => $link['active'], 'text-white/80 hover:bg-white/10 hover:text-white' => ! $link['active']])>{{ $link['label'] }}</a>
                @endforeach
                <span class="mx-2 h-5 w-px bg-white/20" aria-hidden="true"></span>
                <x-locale-switcher class="gap-1 text-xs [&_a]:rounded-md [&_a]:px-2 [&_a]:py-1.5 [&_a]:font-bold [&_a]:text-white/70 [&_a:hover]:text-white [&_a[aria-current]]:bg-white/15 [&_a[aria-current]]:text-white" />
                <span class="max-w-[10rem] truncate px-3 text-sm text-white/70">{{ $user->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-bold text-white/80 hover:bg-white/10 hover:text-white focus-visible:ring-offset-ink">{{ __('app.nav.logout') }}</button>
                </form>
            </nav>

            <button type="button" class="-mr-2 inline-flex h-11 w-11 items-center justify-center rounded-lg hover:bg-white/10 focus-visible:ring-offset-ink md:hidden"
                    aria-controls="menu-mobile" x-on:click="toggle" x-bind:aria-expanded="expanded" x-bind:aria-label="toggleLabel" aria-label="{{ __('app.nav.open_menu') }}" aria-expanded="false">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path x-show="closed" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>

        <div id="menu-mobile" hidden x-bind:hidden="closed" class="border-t border-white/10 bg-ink px-4 pb-4 pt-2 md:hidden">
            <nav class="flex flex-col" aria-label="{{ __('app.nav.menu') }}">
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" @if ($link['active']) aria-current="page" @endif
                       @class(['flex min-h-[3rem] items-center rounded-lg px-3 text-base font-bold focus-visible:ring-offset-ink', 'bg-white/15 text-white' => $link['active'], 'text-white/85' => ! $link['active']])>{{ $link['label'] }}</a>
                @endforeach
            </nav>
            <div class="mt-2 flex items-center justify-between gap-3 border-t border-white/10 px-3 pt-3">
                <div class="min-w-0">
                    <p class="truncate text-sm text-white/70">{{ $user->name }}</p>
                    <x-locale-switcher class="mt-1 gap-2 text-sm [&_a]:inline-flex [&_a]:min-h-touch [&_a]:min-w-touch [&_a]:items-center [&_a]:justify-center [&_a]:rounded-lg [&_a]:font-bold [&_a]:text-white/70 [&_a[aria-current]]:bg-white/15 [&_a[aria-current]]:text-white" />
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn border border-white/30 text-white hover:bg-white/10 focus-visible:ring-offset-ink">{{ __('app.nav.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main id="conteudo" class="mx-auto {{ $width }} px-4 pb-16 pt-6 sm:pt-8">
        <x-flash />
        {{ $slot }}
    </main>
</x-layouts.base>
