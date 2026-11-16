<x-admin.layout :title="__('admin.home.settings')">
    <p><a href="{{ route('admin.home.index') }}">&larr; {{ __('admin.home.title') }}</a></p>
    <h1>{{ __('admin.home.settings') }}</h1>
    <p class="adm-muted">{{ __('admin.home.settings_hint') }}</p>

    <form method="POST" action="{{ route('admin.home.settings.update') }}" class="adm-card adm-form">
        @csrf
        @method('PUT')

        @foreach ($definitions as $key => $def)
            @foreach ($def['per_locale'] ? config('photoparty.locales') : [null] as $locale)
                @php
                    $field = $locale ? "{$key}_{$locale}" : $key;
                    $label = __('admin.home.settings_fields.'.$key).($locale ? ' ('.\App\Support\HomeSchema::localeLabel($locale).')' : '');
                @endphp
                <div class="adm-field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    @if ($key === 'seo_description')
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="{{ $def['max'] }}">{{ old($field, $values[$field] ?? '') }}</textarea>
                    @else
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ ! empty($def['email']) ? 'email' : 'text' }}" maxlength="{{ $def['max'] }}" value="{{ old($field, $values[$field] ?? '') }}">
                    @endif
                </div>
            @endforeach
        @endforeach

        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
        </div>
    </form>
</x-admin.layout>
