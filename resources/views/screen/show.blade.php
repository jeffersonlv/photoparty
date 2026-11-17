{{--
    Telão: /screen/{token}. Recebe $event, $qrImage (data URI ou null), $ended, $trial, $demo, $config, $settings.
    CSP estrita: sem script/estilo inline. Os dados vão num bloco JSON (não executa) lido por
    resources/js/screen/index.js. Tudo impresso com {{ }}.
--}}
@php
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#000000">
    <title>{{ $event->name }}</title>
    @vite(['resources/css/screen.css', 'resources/js/screen/index.js'])
    <script type="application/json" id="pp-screen">@json($config, $jsonFlags)</script>
</head>
<body class="sc-body">
    <noscript><p class="sc-noscript">{{ __('screen.noscript') }}</p></noscript>

    <main id="sc-root" class="sc" data-transition="{{ $settings['transition'] }}">
        <div class="sc-stage" aria-live="off">
            <img id="sc-layer-a" class="sc-layer" alt="" decoding="async">
            <img id="sc-layer-b" class="sc-layer" alt="" decoding="async">
        </div>

        <div id="sc-empty" class="sc-empty">
            <p class="sc-empty__name">{{ $event->name }}</p>
            {{-- A 1ª frase aparece já no HTML; o JS gira as demais (lista abaixo, sem script inline). --}}
            @php($phrases = (array) __('screen.phrases'))
            <p id="sc-phrase" class="sc-empty__text sc-phrase" aria-live="polite">{{ $phrases[0] ?? __('screen.waiting') }}</p>
            <ul id="sc-phrases" hidden>
                @foreach ($phrases as $phrase)
                    <li>{{ $phrase }}</li>
                @endforeach
            </ul>
        </div>

        @if ($qrImage)
            <figure class="sc-qr">
                <img src="{{ $qrImage }}" alt="QR code" width="160" height="160">
                <figcaption>{{ __('screen.scan') }}</figcaption>
            </figure>
        @endif

        @if ($demo)
            <div class="sc-demo" aria-hidden="true"><span>{{ __('screen.demo_banner') }}</span></div>
        @elseif ($trial)
            <div class="sc-seal">{{ __('screen.brand') }}</div>
        @endif

        <p id="sc-ended" class="sc-badge sc-badge--ended" @unless ($ended) hidden @endunless>{{ __('screen.ended_notice') }}</p>
        <p id="sc-offline" class="sc-badge sc-badge--offline" hidden>{{ __('screen.offline') }}</p>
        <div id="sc-stopped" class="sc-stopped" hidden>
            <p>{{ __('screen.stopped') }}</p>
        </div>

        <button type="button" id="sc-fullscreen" class="sc-fs" data-enter="{{ __('screen.fullscreen') }}" data-exit="{{ __('screen.exit_fullscreen') }}">{{ __('screen.fullscreen') }}</button>
    </main>
</body>
</html>
