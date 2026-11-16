<x-home.layout :title="__('home.legal.'.$page.'.title').' · '.__('app.name')" :index="false">
    <article class="hm-wrap hm-legal">
        <p class="hm-legal__notice" role="note">{{ __('home.legal.draft_notice') }}</p>
        <h1>{{ __('home.legal.'.$page.'.title') }}</h1>
        <p class="hm-footer__muted">{{ __('home.legal.updated_placeholder') }}</p>
        @foreach (__('home.legal.'.$page.'.sections') as $block)
            <h2>{{ $block['heading'] }}</h2>
            <p>{{ $block['body'] }}</p>
        @endforeach
        <p><a class="hm-link" href="{{ route('home') }}">&larr; {{ __('home.legal.back') }}</a></p>
    </article>
</x-home.layout>
