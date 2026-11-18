{{--
    Páginas de erro genéricas (OWASP A02/A10): só código + mensagem curta, sem detalhes técnicos.
    Substitui o errors::minimal do Laravel, que usa <style> inline (bloqueado pela CSP "style-src 'self'").
    As views 401/403/404/419/429/500/503 do framework estendem esta.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · Photo Party</title>
    @vite(['resources/css/fonts.css', 'resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center px-6 text-center">
        <p class="text-6xl font-extrabold text-brand-700">@yield('code')</p>
        <h1 class="mt-4 text-xl font-bold">@yield('message')</h1>
        <a class="mt-8 font-bold text-brand-700 underline" href="{{ route('home', absolute: false) }}">Photo Party</a>
    </main>
</body>
</html>
