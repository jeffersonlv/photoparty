@props(['title' => null, 'description' => null, 'settings' => [], 'preview' => false, 'index' => true])
@php
    $user = auth()->user();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:title" content="{{ $title ?? __('app.name') }}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#181330">
    <meta name="robots" content="{{ $index && ! $preview ? 'index, follow' : 'noindex, nofollow' }}">
    @vite(['resources/css/fonts.css', 'resources/css/home.css', 'resources/js/app.js'])
</head>
<body class="hm">
    @if ($preview)
        <div class="hm-preview">{{ __('admin.home.preview_banner') }}</div>
    @endif

    <header class="hm-header" x-data="navMenu" x-on:keydown.escape.window="close"
            data-open-label="{{ __('app.nav.open_menu') }}" data-close-label="{{ __('app.nav.close_menu') }}">
        <div class="hm-wrap hm-header__inner">
            <a class="hm-brand" href="{{ $preview ? '#' : route('home') }}">
                <svg class="hm-logo" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><rect width="32" height="32" rx="9" fill="#5b45e8"/><circle cx="16" cy="17" r="8" fill="#fff"/><circle cx="16" cy="17" r="4.6" fill="#181330"/><circle cx="14.4" cy="15.4" r="1.4" fill="#fff"/><circle cx="25" cy="7.5" r="3" fill="#ffd23f"/></svg>
                <span>{{ __('app.name') }}</span>
            </a>
            @unless ($preview)
                @auth
                    @if ($user->isAdmin())
                        <a class="hm-btn hm-btn--ghost hm-btn--sm hm-header__cta" href="{{ route('admin.accounts.index') }}">{{ __('home.nav.admin') }}</a>
                    @else
                        <a class="hm-btn hm-btn--primary hm-btn--sm hm-header__cta" href="{{ route('dashboard') }}">{{ __('home.nav.dashboard') }}</a>
                    @endif
                @else
                    <a class="hm-btn hm-btn--primary hm-btn--sm hm-header__cta" href="{{ route('register') }}">{{ __('home.nav.register') }}</a>
                @endauth
                <button type="button" class="hm-burger" aria-controls="hm-menu" aria-expanded="false" aria-label="{{ __('app.nav.open_menu') }}"
                        x-on:click="toggle" x-bind:aria-expanded="expanded" x-bind:aria-label="toggleLabel">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path x-show="closed" d="M4 7h16M4 12h16M4 17h16"/>
                        <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
                <nav id="hm-menu" class="hm-menu" x-bind:data-open="expanded" aria-label="{{ __('home.nav.menu') }}">
                    @guest
                        <a class="hm-link" href="{{ route('login') }}">{{ __('home.nav.login') }}</a>
                    @endguest
                    <div class="hm-lang">
                        @foreach (config('photoparty.locales') as $code)
                            <a href="{{ route('locale', $code) }}" lang="{{ str_replace('_', '-', $code) }}" @class(['is-active' => $locale === $code]) @if ($locale === $code) aria-current="true" @endif>{{ $code === 'pt_BR' ? 'PT' : 'EN' }}</a>
                        @endforeach
                    </div>
                </nav>
            @endunless
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    {{-- Rodapé legal: SEMPRE presente, não é uma seção desligável. --}}
    <footer class="hm-footer" id="footer">
        <div class="hm-wrap hm-footer__inner">
            <div>
                <strong class="hm-footer__brand">{{ __('app.name') }}</strong>
                <p class="hm-footer__muted">&copy; {{ now()->year }} {{ __('app.name') }}. {{ __('home.footer.rights') }}</p>
            </div>
            <nav class="hm-footer__links" aria-label="Legal">
                <a href="{{ route('legal.terms') }}">{{ __('home.footer.terms') }}</a>
                <a href="{{ route('legal.privacy') }}">{{ __('home.footer.privacy') }}</a>
                @if (! empty($settings['contact_email']))
                    <a href="mailto:{{ $settings['contact_email'] }}">{{ __('home.footer.contact') }}: {{ $settings['contact_email'] }}</a>
                @endif
                @if (! empty($settings['contact_phone']))
                    <span>{{ $settings['contact_phone'] }}</span>
                @endif
            </nav>
        </div>
    </footer>
</body>
</html>
