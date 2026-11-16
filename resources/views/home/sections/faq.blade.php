<section class="hm-section" id="faq">
    <div class="hm-wrap hm-wrap--narrow">
        <header class="hm-section__head">
            <h2>{{ $content['title'] ?? '' }}</h2>
        </header>
        <div class="hm-faq">
            @foreach ($items as $item)
                <details class="hm-faq__item">
                    <summary>{{ $item['question'] ?? '' }}</summary>
                    <p>{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
