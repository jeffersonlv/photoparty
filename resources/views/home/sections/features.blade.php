<section class="hm-section" id="features">
    <div class="hm-wrap">
        <header class="hm-section__head">
            <h2>{{ $content['title'] ?? '' }}</h2>
            @if (! empty($content['subtitle']))
                <p class="hm-lead">{{ $content['subtitle'] }}</p>
            @endif
        </header>
        <div class="hm-cards">
            @foreach ($items as $item)
                <article class="hm-card">
                    <h3>{{ $item['title'] ?? '' }}</h3>
                    <p>{{ $item['text'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
