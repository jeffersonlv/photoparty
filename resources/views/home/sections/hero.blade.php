<section class="hm-hero" id="hero">
    <div class="hm-wrap hm-hero__inner">
        <div class="hm-hero__copy">
            @if (! empty($content['eyebrow']))
                <p class="hm-eyebrow">{{ $content['eyebrow'] }}</p>
            @endif
            <h1>{{ $content['title'] ?? '' }}</h1>
            @if (! empty($content['subtitle']))
                <p class="hm-lead">{{ $content['subtitle'] }}</p>
            @endif
            <div class="hm-hero__cta">
                @if (! empty($content['cta_primary']))
                    <a class="hm-btn hm-btn--primary hm-btn--lg" href="{{ route('register') }}">{{ $content['cta_primary'] }}</a>
                @endif
                @if (! empty($content['cta_secondary']))
                    <a class="hm-btn hm-btn--ghost hm-btn--lg" href="#how_it_works">{{ $content['cta_secondary'] }}</a>
                @endif
            </div>
        </div>

        @php
            // Demonstração ao vivo (toggle do admin + evento de demo ao vivo); senão, o bloco estático.
            $demoLive = \App\Services\Demo\DemoEvent::enabled() ? app(\App\Services\Demo\DemoEvent::class)->heroData() : null;
            $demoConfig = $demoLive ? ['pollUrl' => $demoLive['pollUrl'], 'pollMs' => $demoLive['pollMs'], 'max' => $demoLive['max'], 'alt' => __('home.hero.demo_photo_alt')] : null;
            $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        @endphp

        @if ($demoLive)
            {{-- QR do link do convidado do evento de demo + mini-telão (polling de 5 s em resources/js/home/demo.js). --}}
            <div class="hm-demo hm-demo--live" id="live-demo" data-demo="live">
                <div class="hm-demo__screen" id="demo-screen" role="group" aria-label="{{ __('home.hero.screen_label') }}">
                    <span class="hm-demo__tag">{{ __('home.hero.screen_label') }}</span>
                    <div class="hm-demo__grid" aria-hidden="true">
                        <span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <div class="hm-demo__stage" id="demo-stage"></div>
                    <p class="hm-demo__empty" id="demo-empty-text" role="status">{{ __('home.hero.demo_empty') }}</p>
                </div>
                <div class="hm-demo__scan">
                    <img class="hm-demo__qrimg" src="{{ $demoLive['qr'] }}" width="124" height="124" alt="{{ __('home.hero.qr_label') }}">
                    <div class="hm-demo__scantext">
                        <p class="hm-demo__scantitle">{{ __('home.hero.demo_scan') }}</p>
                        <a class="hm-demo__link" href="{{ $demoLive['guestUrl'] }}" target="_blank" rel="noopener">{{ __('home.hero.demo_open') }}</a>
                    </div>
                </div>
                <p class="hm-demo__privacy">{{ __('home.hero.demo_privacy') }}</p>
                {{-- demo_note ("em breve") só vale para o bloco estático. --}}
                <script type="application/json" id="pp-demo">@json($demoConfig, $jsonFlags)</script>
            </div>
            @vite('resources/js/home/demo.js')
        @else
            {{-- Bloco estático: demonstração desligada no admin ou evento de demo ausente. --}}
            <div class="hm-demo" id="live-demo" data-demo="placeholder">
                <div class="hm-demo__screen" role="img" aria-label="{{ __('home.hero.screen_label') }}">
                    <div class="hm-demo__grid">
                        <span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <span class="hm-demo__tag">{{ __('home.hero.screen_label') }}</span>
                </div>
                <div class="hm-demo__qr" role="img" aria-label="{{ __('home.hero.qr_label') }}">
                    <svg viewBox="0 0 7 7" width="72" height="72" aria-hidden="true" focusable="false" shape-rendering="crispEdges">
                        <path fill="currentColor" d="M0 0h3v3H0zM4 0h3v3H4zM0 4h3v3H0z"/>
                        <path fill="#fff" d="M1 1h1v1H1zM5 1h1v1H5zM1 5h1v1H1z"/>
                        <path fill="currentColor" d="M4 4h1v1H4zM6 4h1v1H6zM5 5h1v1H5zM4 6h1v1H4zM6 6h1v1H6z"/>
                    </svg>
                </div>
                @if (! empty($content['demo_note']))
                    <p class="hm-demo__note">{{ $content['demo_note'] }}</p>
                @endif
            </div>
        @endif
    </div>
</section>
