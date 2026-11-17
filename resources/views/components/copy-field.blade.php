@props(['value', 'label'])
{{-- Link somente leitura com botão de copiar (Alpine.data('copyField')). --}}
<div x-data="copyField" data-copy="{{ __('app.nav.copy') }}" data-copied="{{ __('app.nav.copied') }}" {{ $attributes }}>
    <label class="meta-label" for="cf-{{ md5($label) }}">{{ $label }}</label>
    <div class="mt-1 flex gap-2">
        <input id="cf-{{ md5($label) }}" x-ref="field" x-on:focus="select" type="text" readonly value="{{ $value }}" class="input mt-0 min-w-0 flex-1 font-mono text-xs sm:text-sm">
        <button type="button" x-on:click="copy" class="btn btn-secondary shrink-0 px-4"><span x-text="label">{{ __('app.nav.copy') }}</span></button>
    </div>
    <p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>
</div>
