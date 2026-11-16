@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('admin.title') }} · {{ __('app.name') }}</title>
    @vite(['resources/css/admin.css'])
</head>
<body class="adm">
    <header class="adm-top">
        <div class="adm-top__inner">
            <a class="adm-brand" href="{{ route('admin.dashboard') }}">{{ __('app.name') }} <span>{{ __('admin.title') }}</span></a>
            <nav class="adm-nav" aria-label="{{ __('admin.title') }}">
                @foreach (['accounts' => 'admin.accounts.*', 'plans' => 'admin.plans.*', 'frames' => 'admin.frames.*', 'home' => 'admin.home.*', 'audit' => 'admin.audit.*'] as $item => $pattern)
                    <a href="{{ route('admin.'.$item.'.index') }}" @class(['is-active' => request()->routeIs($pattern)])>{{ __('admin.nav.'.$item) }}</a>
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
    </header>
    <main class="adm-main">
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
