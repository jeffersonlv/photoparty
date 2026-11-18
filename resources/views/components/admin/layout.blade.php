@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#181330">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('admin.title') }} · {{ __('app.name') }}</title>
    @vite(['resources/css/fonts.css', 'resources/css/admin.css', 'resources/js/app.js'])
</head>
<body class="adm">
    <a href="#conteudo" class="adm-skip">{{ __('app.nav.skip') }}</a>
    <header class="adm-top" x-data="navMenu" x-on:keydown.escape.window="close"
            data-open-label="{{ __('app.nav.open_menu') }}" data-close-label="{{ __('app.nav.close_menu') }}">
        <div class="adm-top__inner">
            <a class="adm-brand" href="{{ route('admin.dashboard') }}">
                <svg class="adm-logo" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><rect width="32" height="32" rx="9" fill="#5b45e8"/><circle cx="16" cy="17" r="8" fill="#fff"/><circle cx="16" cy="17" r="4.6" fill="#181330"/><circle cx="25" cy="7.5" r="3" fill="#ffd23f"/></svg>
                <strong>{{ __('app.name') }}</strong> <span>{{ __('admin.title') }}</span>
            </a>
            <button type="button" class="adm-burger" aria-controls="adm-menu" aria-expanded="false" aria-label="{{ __('app.nav.open_menu') }}"
                    x-on:click="toggle" x-bind:aria-expanded="expanded" x-bind:aria-label="toggleLabel">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path x-show="closed" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
            <div id="adm-menu" class="adm-menu" x-bind:data-open="expanded">
                <nav class="adm-nav" aria-label="{{ __('admin.title') }}">
                    @foreach (['accounts' => ['admin.accounts.index', 'admin.accounts.*'], 'plans' => ['admin.plans.index', 'admin.plans.*'], 'frames' => ['admin.frames.index', 'admin.frames.*'], 'home' => ['admin.home.index', 'admin.home.*'], 'privacy' => ['admin.privacy.requests.index', 'admin.privacy.*'], 'audit' => ['admin.audit.index', 'admin.audit.*']] as $item => [$routeName, $pattern])
                        <a href="{{ route($routeName) }}" @class(['is-active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ __('admin.nav.'.$item) }}</a>
                    @endforeach
                </nav>
                <div class="adm-top__right">
                    <x-locale-switcher />
                    <a href="{{ route('home') }}">{{ __('admin.nav.view_site') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="adm-linkbtn">{{ __('admin.nav.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <main class="adm-main" id="conteudo">
        @if (session('status'))
            <div class="adm-alert adm-alert--ok" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="adm-alert adm-alert--err" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="adm-alert adm-alert--err" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
