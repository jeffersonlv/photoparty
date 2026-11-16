{{--
    Pagina de aviso do convidado (evento agendado ou encerrado). Sem JavaScript.
    Recebe $title (nome do evento) e $message (texto ja traduzido). Tudo via {{ }}.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f1020">
    <title>{{ $title }}</title>
    @vite(['resources/css/fonts.css', 'resources/css/guest.css'])
</head>
<body class="gp-body">
    <main class="gp">
        <section class="gp-step gp-step--center">
            <p class="gp-lead">{{ $title }}</p>
            <h1 class="gp-title">{{ $message }}</h1>
        </section>
    </main>
</body>
</html>
