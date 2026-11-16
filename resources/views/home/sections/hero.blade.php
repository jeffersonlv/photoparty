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

        {{-- Espaço reservado da demonstração ao vivo (QR + mini-telão). Será ligado a um evento de demo. --}}
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
    </div>
</section>
