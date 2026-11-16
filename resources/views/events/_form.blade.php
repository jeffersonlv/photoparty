@php
    $isTrial = $event->exists ? $event->is_trial : $account->isOnTrial();
    $filters = old('enabled_filters', $event->enabled_filters ?? []);
@endphp

@if ($isTrial)
    <p class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('app.events.trial_notice') }}</p>
@endif

<div class="space-y-5">
    <div>
        <x-label for="name">{{ ucfirst(__('app.fields.event_name')) }}</x-label>
        <input id="name" name="name" type="text" value="{{ old('name', $event->name) }}" required maxlength="120" class="input">
        <x-input-error name="name" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-label for="starts_at">{{ ucfirst(__('app.fields.starts_at')) }}</x-label>
            <input id="starts_at" name="starts_at" type="datetime-local" required class="input"
                   value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}">
            <x-input-error name="starts_at" />
        </div>
        <div>
            <x-label for="ends_at">{{ ucfirst(__('app.fields.ends_at')) }}</x-label>
            <input id="ends_at" name="ends_at" type="datetime-local" required class="input"
                   value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}">
            <x-input-error name="ends_at" />
        </div>
        <p class="text-xs text-slate-500 sm:col-span-2">{{ __('app.events.timezone_hint', ['tz' => config('app.timezone')]) }}</p>
    </div>

    <fieldset>
        <legend class="block text-sm font-medium text-slate-700">{{ ucfirst(__('app.fields.moderation')) }}</legend>
        @foreach (['pre', 'post'] as $mode)
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input type="radio" name="moderation" value="{{ $mode }}" @checked(old('moderation', $event->moderation ?? 'pre') === $mode)>
                {{ __('app.moderation.'.$mode) }}
            </label>
        @endforeach
        <x-input-error name="moderation" />
    </fieldset>

    <div>
        <x-label for="welcome_message">{{ ucfirst(__('app.fields.welcome_message')) }}</x-label>
        <textarea id="welcome_message" name="welcome_message" rows="3" maxlength="500" class="input">{{ old('welcome_message', $event->welcome_message) }}</textarea>
        <x-input-error name="welcome_message" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="ask_guest_name" value="0">
            <input type="checkbox" name="ask_guest_name" value="1" class="checkbox" @checked(old('ask_guest_name', $event->ask_guest_name))>
            {{ __('app.fields.ask_guest_name') }}
        </label>

        <div>
            <x-label for="max_photos_per_guest">{{ ucfirst(__('app.fields.max_photos_per_guest')) }}</x-label>
            <input id="max_photos_per_guest" name="max_photos_per_guest" type="number" min="1" max="100" class="input"
                   value="{{ old('max_photos_per_guest', $event->max_photos_per_guest) }}">
            <x-input-error name="max_photos_per_guest" />
        </div>
    </div>

    <fieldset>
        <legend class="block text-sm font-medium text-slate-700">{{ ucfirst(__('app.fields.enabled_filters')) }}</legend>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach (config('photoparty.filters') as $filter)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="enabled_filters[]" value="{{ $filter }}" class="checkbox" @checked(in_array($filter, (array) $filters, true))>
                    {{ __('app.filters.'.$filter) }}
                </label>
            @endforeach
        </div>
        <x-input-error name="enabled_filters" />
    </fieldset>

    <div>
        <x-label for="language">{{ ucfirst(__('app.fields.language')) }}</x-label>
        <select id="language" name="language" class="input">
            @foreach (config('photoparty.locales') as $locale)
                <option value="{{ $locale }}" @selected(old('language', $event->language) === $locale)>{{ __('app.locales.'.$locale) }}</option>
            @endforeach
        </select>
        <x-input-error name="language" />
    </div>

    {{-- Molduras: módulo 8. --}}
</div>
