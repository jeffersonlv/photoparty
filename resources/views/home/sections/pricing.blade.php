<section class="hm-section hm-section--alt" id="pricing">
    <div class="hm-wrap">
        <header class="hm-section__head">
            <h2>{{ $content['title'] ?? '' }}</h2>
            @if (! empty($content['subtitle']))
                <p class="hm-lead">{{ $content['subtitle'] }}</p>
            @endif
        </header>
        <div class="hm-plans">
            @foreach ($plans as $plan)
                <article class="hm-plan">
                    <h3>{{ $plan->name }}</h3>
                    <p class="hm-plan__price">
                        @if ((float) $plan->price > 0)
                            <span>{{ $plan->currency === 'EUR' ? '€' : ($plan->currency === 'BRL' ? 'R$' : $plan->currency.' ') }}{{ number_format((float) $plan->price, 2, app()->getLocale() === 'en' ? '.' : ',', app()->getLocale() === 'en' ? ',' : '.') }}</span>
                        @else
                            <span>{{ __('home.pricing.free') }}</span>
                        @endif
                    </p>
                    <ul>
                        <li>{{ $plan->max_events_per_month ? __('home.pricing.events_per_month', ['count' => $plan->max_events_per_month]) : __('home.pricing.events_unlimited') }}</li>
                        <li>{{ __('home.pricing.photos_per_event', ['count' => $plan->max_photos_per_event]) }}</li>
                        <li>{{ __('home.pricing.retention', ['count' => $plan->retention_days]) }}</li>
                        @if ($plan->allows_custom_frames)
                            <li>{{ __('home.pricing.custom_frames') }}</li>
                        @endif
                        @if ($plan->white_label)
                            <li>{{ __('home.pricing.white_label') }}</li>
                        @endif
                    </ul>
                    @if (! empty($content['cta']))
                        <a class="hm-btn hm-btn--primary" href="{{ route('register') }}">{{ $content['cta'] }}</a>
                    @endif
                </article>
            @endforeach
        </div>
        @if (! empty($content['note']))
            <p class="hm-note">{{ $content['note'] }}</p>
        @endif
    </div>
</section>
