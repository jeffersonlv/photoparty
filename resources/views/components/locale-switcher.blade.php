<div {{ $attributes->merge(['class' => 'flex gap-2 text-xs']) }}>
    @foreach (config('photoparty.locales') as $locale)
        <a href="{{ route('locale', $locale) }}"
           @if (app()->getLocale() === $locale) aria-current="true" @endif
           @class(['font-bold text-ink' => app()->getLocale() === $locale, 'text-muted hover:text-ink' => app()->getLocale() !== $locale])
           lang="{{ str_replace('_', '-', $locale) }}">{{ $locale === 'pt_BR' ? 'PT' : 'EN' }}</a>
    @endforeach
</div>
