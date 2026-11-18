{{--
    Pagina do convidado: /e/{slug}?t={upload_token}
    Recebe $event (array) com: name, welcome, moderation (bool), frames [{key, name, configUrl, texts?}],
    uploadUrl, language, askGuestName.
    Todo dado do evento vai para o JS como bloco JSON com flags HEX (seguro dentro do bloco de dados);
    nada do evento e impresso como HTML cru.
--}}
@php
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $locale = str_replace('_', '-', $event['language'] ?? app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f1020">
    <title>{{ $event['name'] }}</title>
    @vite(['resources/css/fonts.css', 'resources/css/guest.css', 'resources/js/guest/index.js'])
    {{-- Blocos de dados (nao executam): passam pela CSP script-src 'self'. JSON_HEX_* impede
         fechar a tag do bloco ou injetar HTML por dentro do JSON. Lidos em index.js. --}}
    <script type="application/json" id="pp-event">@json($event, $jsonFlags)</script>
    <script type="application/json" id="pp-i18n">@json(__('guest'), $jsonFlags)</script>
</head>
<body class="gp-body">
    <noscript><p class="gp-noscript">{{ __('guest.noscript') }}</p></noscript>
    @include('guest.partials.app')
</body>
</html>
