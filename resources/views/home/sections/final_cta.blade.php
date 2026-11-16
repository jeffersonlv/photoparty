<section class="hm-final" id="final_cta">
    <div class="hm-wrap hm-wrap--narrow">
        <h2>{{ $content['title'] ?? '' }}</h2>
        @if (! empty($content['subtitle']))
            <p class="hm-lead">{{ $content['subtitle'] }}</p>
        @endif
        @if (! empty($content['cta']))
            <a class="hm-btn hm-btn--light hm-btn--lg" href="{{ route('register') }}">{{ $content['cta'] }}</a>
        @endif
    </div>
</section>
