@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#181330">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ __('app.name') }}</title>
    @vite(['resources/css/fonts.css', 'resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-canvas text-ink antialiased">
    {{ $slot }}
</body>
</html>
