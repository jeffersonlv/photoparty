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
    <meta name="robots" content="{{ $index && ! $preview ? 'index, follow' : 'noindex, nofollow' }}">
    @vite(['resources/css/home.css'])
</head>
<body class="hm">
    @if ($preview)
        <div class="hm-preview">{{ __('admin.home.preview_banner') }}</div>
    @endif

    <header class="hm-header">
        <div class="hm-wrap hm-header__inner">
            <a class="hm-brand" href="{{ $preview ? '#' : route('home') }}">{{ __('app.name') }}</a>
            @unless ($preview)
                <nav class="hm-header__nav" aria-label="{{ __('home.nav.menu') }}">
                    <div class="hm-lang">
                        @foreach (config('photoparty.locales') as $code)
                            <a href="{{ route('locale', $code) }}" lang="{{ str_replace('_', '-', $code) }}" @class(['is-active' => $locale === $code])>{{ $code === 'pt_BR' ? 'PT' : 'EN' }}</a>
                        @endforeach
                    </div>
                    @auth
                        @if ($user->isAdmin())
                            <a class="hm-btn hm-btn--ghost" href="{{ route('admin.accounts.index') }}">{{ __('home.nav.admin') }}</a>
                        @else
                            <a class="hm-btn hm-btn--primary" href="{{ route('dashboard') }}">{{ __('home.nav.dashboard') }}</a>
                        @endif
                    @else
                        <a class="hm-link" href="{{ route('login') }}">{{ __('home.nav.login') }}</a>
                        <a class="hm-btn hm-btn--primary" href="{{ route('register') }}">{{ __('home.nav.register') }}</a>
                    @endauth
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
                <strong>{{ __('app.name') }}</strong>
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
