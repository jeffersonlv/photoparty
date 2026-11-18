<x-admin.layout :title="__('admin.privacy.settings')">
    <p><a href="{{ route('admin.privacy.requests.index') }}">&larr; {{ __('admin.privacy.title') }}</a></p>
    <h1>{{ __('admin.privacy.settings') }}</h1>
    <p class="adm-muted">{{ __('admin.privacy.settings_hint') }}</p>

    @if ($missing !== [])
        <div class="adm-alert adm-alert--err" role="alert">
            {{ __('admin.privacy.missing_warning', ['fields' => implode(', ', array_map(fn ($k) => __('admin.privacy.fields.'.$k), $missing))]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.privacy.settings.update') }}" class="adm-card adm-form">
        @csrf
        @method('PUT')
        @foreach ($fields as $key => $def)
            <div class="adm-field">
                <label for="{{ $key }}">{{ __('admin.privacy.fields.'.$key) }}</label>
                @if (! empty($def['multiline']))
                    <textarea id="{{ $key }}" name="{{ $key }}" rows="4" maxlength="{{ $def['max'] }}">{{ old($key, $values[$key] ?? '') }}</textarea>
                @else
                    <input id="{{ $key }}" name="{{ $key }}" type="{{ ! empty($def['email']) ? 'email' : 'text' }}" maxlength="{{ $def['max'] }}" value="{{ old($key, $values[$key] ?? '') }}">
                @endif
                @if ($key === 'privacy_contact_email')
                    <small class="adm-muted">{{ __('admin.privacy.config_email_hint', ['email' => $configEmail !== '' ? $configEmail : __('admin.privacy.none')]) }}</small>
                @endif
            </div>
        @endforeach
        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
        </div>
    </form>
</x-admin.layout>
