<section class="hm-section" id="how_it_works">
    <div class="hm-wrap">
        <header class="hm-section__head">
            <h2>{{ $content['title'] ?? '' }}</h2>
            @if (! empty($content['subtitle']))
                <p class="hm-lead">{{ $content['subtitle'] }}</p>
            @endif
        </header>
        <ol class="hm-steps">
            @foreach ($items as $item)
                <li class="hm-step">
                    <span class="hm-step__n" aria-hidden="true">{{ $loop->iteration }}</span>
                    <h3>{{ $item['title'] ?? '' }}</h3>
                    <p>{{ $item['text'] ?? '' }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
