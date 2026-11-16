<div {{ $attributes->merge(['class' => 'flex gap-2 text-xs']) }}>
    @foreach (config('photoparty.locales') as $locale)
        <a href="{{ route('locale', $locale) }}"
           @class(['font-semibold text-slate-900' => app()->getLocale() === $locale, 'text-slate-500 hover:text-slate-900' => app()->getLocale() !== $locale])
           lang="{{ str_replace('_', '-', $locale) }}">{{ $locale === 'pt_BR' ? 'PT' : 'EN' }}</a>
    @endforeach
</div>
