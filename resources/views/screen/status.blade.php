{{--
    Telão de evento agendado: aviso "começa em...". Sem JavaScript; recarrega sozinho a cada 30 s
    (meta refresh é permitido pela CSP) e vira o telão quando o evento começa.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="refresh" content="30">
    <title>{{ $title }}</title>
    @vite(['resources/css/screen.css'])
</head>
<body class="sc-body">
    <main class="sc-notice">
        <p class="sc-notice__lead">{{ $title }}</p>
        <h1 class="sc-notice__title">{{ $message }}</h1>
    </main>
</body>
</html>
