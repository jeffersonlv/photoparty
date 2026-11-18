@php
    $isTrial = $event->exists ? $event->is_trial : $account->isOnTrial();
    $filters = old('enabled_filters', $event->enabled_filters ?? []);
@endphp

@if ($isTrial)
    <p class="alert alert-warn mb-6" role="note">{{ __('app.events.trial_notice') }}</p>
@endif

<div class="space-y-6">
    <div>
        <x-label for="name">{{ ucfirst(__('app.fields.event_name')) }}</x-label>
        <input id="name" name="name" type="text" value="{{ old('name', $event->name) }}" required maxlength="120" class="input">
        <x-input-error name="name" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
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
        <p class="text-xs text-muted sm:col-span-2">{{ __('app.events.timezone_hint', ['tz' => config('app.timezone')]) }}</p>
    </div>

    <fieldset>
        <legend class="field-label">{{ ucfirst(__('app.fields.moderation')) }}</legend>
        <div class="mt-2 grid gap-2">
            @foreach (['pre', 'post'] as $mode)
                <label class="choice">
                    <input type="radio" name="moderation" value="{{ $mode }}" class="h-5 w-5 shrink-0 border-slate-400 text-brand-600 focus:ring-brand-600" @checked(old('moderation', $event->moderation ?? 'pre') === $mode)>
                    {{ __('app.moderation.'.$mode) }}
                </label>
            @endforeach
        </div>
        <x-input-error name="moderation" />
    </fieldset>

    <div>
        <x-label for="welcome_message">{{ ucfirst(__('app.fields.welcome_message')) }}</x-label>
        <textarea id="welcome_message" name="welcome_message" rows="3" maxlength="500" class="input">{{ old('welcome_message', $event->welcome_message) }}</textarea>
        <x-input-error name="welcome_message" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2 sm:items-start">
        <label class="choice sm:mt-7">
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
        <legend class="field-label">{{ ucfirst(__('app.fields.enabled_filters')) }}</legend>
        <div class="mt-2 grid grid-cols-1 gap-2 min-[400px]:grid-cols-2 sm:grid-cols-3">
            @foreach (config('photoparty.filters') as $filter)
                <label class="choice">
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

    {{-- Geolocalização: "envio só no local do evento" (recurso de plano; servidor decide). --}}
    @php
        $geoAllowed = $account->canUseGeofence();
        $geoCfg = config('photoparty.geofence');
    @endphp
    <fieldset class="rounded-xl border border-line p-4" data-geofence
              @if ($geoAllowed)
                  x-data="geofenceForm"
                  data-locating="{{ __('app.geofence.locating') }}"
                  data-located="{{ __('app.geofence.located') }}"
                  data-denied="{{ __('app.geofence.locate_denied') }}"
                  data-failed="{{ __('app.geofence.locate_failed') }}"
                  data-unsupported="{{ __('app.geofence.locate_unsupported') }}"
              @else
                  disabled aria-describedby="geofence-unavailable"
              @endif>
        <legend class="field-label px-1">{{ __('app.geofence.title') }}</legend>

        @unless ($geoAllowed)
            <p id="geofence-unavailable" class="alert alert-warn mt-2" role="note">{{ __('app.geofence.unavailable') }}</p>
        @endunless

        <label class="choice mt-2">
            <input type="hidden" name="geofence_enabled" value="0">
            <input type="checkbox" name="geofence_enabled" value="1" class="checkbox"
                   @checked($geoAllowed && old('geofence_enabled', $event->geofence_enabled)) @disabled(! $geoAllowed)>
            {{ __('app.geofence.toggle') }}
        </label>
        <p class="mt-1 text-xs text-muted">{{ __('app.geofence.hint') }}</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <x-label for="geofence_radius_m">{{ ucfirst(__('app.geofence.radius')) }}</x-label>
                <input id="geofence_radius_m" name="geofence_radius_m" type="number" class="input"
                       min="{{ $geoCfg['radius_min'] }}" max="{{ $geoCfg['radius_max'] }}" step="1"
                       value="{{ old('geofence_radius_m', $event->geofence_radius_m ?? $geoCfg['radius_default']) }}" @disabled(! $geoAllowed)>
                <p class="mt-1 text-xs text-muted">{{ __('app.geofence.radius_hint', ['min' => $geoCfg['radius_min'], 'max' => $geoCfg['radius_max'], 'default' => $geoCfg['radius_default']]) }}</p>
                <x-input-error name="geofence_radius_m" />
            </div>
            <div class="sm:pt-7">
                @if ($geoAllowed)
                    <button type="button" class="btn btn-secondary btn-block" x-on:click="locate()" x-bind:disabled="busy">{{ __('app.geofence.use_current') }}</button>
                    <p class="mt-1 text-xs text-muted" role="status" aria-live="polite" x-text="message"></p>
                @else
                    <button type="button" class="btn btn-secondary btn-block" disabled>{{ __('app.geofence.use_current') }}</button>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <x-label for="geofence_lat">{{ ucfirst(__('app.geofence.latitude')) }}</x-label>
                <input id="geofence_lat" name="geofence_lat" type="text" inputmode="decimal" class="input" readonly x-ref="lat"
                       value="{{ old('geofence_lat', $event->geofence_lat !== null ? sprintf('%.6f', $event->geofence_lat) : '') }}" @disabled(! $geoAllowed)>
                <x-input-error name="geofence_lat" />
            </div>
            <div>
                <x-label for="geofence_lng">{{ ucfirst(__('app.geofence.longitude')) }}</x-label>
                <input id="geofence_lng" name="geofence_lng" type="text" inputmode="decimal" class="input" readonly x-ref="lng"
                       value="{{ old('geofence_lng', $event->geofence_lng !== null ? sprintf('%.6f', $event->geofence_lng) : '') }}" @disabled(! $geoAllowed)>
                <x-input-error name="geofence_lng" />
            </div>
        </div>

        <div class="mt-4">
            <x-label for="geofence_location">{{ ucfirst(__('app.geofence.paste_label')) }}</x-label>
            <input id="geofence_location" name="geofence_location" type="text" class="input" maxlength="2000" autocomplete="off" x-ref="paste"
                   value="{{ old('geofence_location') }}" @disabled(! $geoAllowed)>
            <p class="mt-1 text-xs text-muted">{{ __('app.geofence.paste_hint') }}</p>
            <x-input-error name="geofence_location" />
        </div>

        <p class="mt-3 text-sm" data-geofence-saved>
            @if ($event->hasGeofenceLocation())
                {{ __('app.geofence.saved', ['coordinates' => $event->geofenceCoordinates()]) }}
                · <a class="font-bold text-brand-700 underline" href="{{ $event->geofenceMapUrl() }}" target="_blank" rel="noopener noreferrer">{{ __('app.geofence.view_map') }}</a>
            @else
                <span class="text-muted">{{ __('app.geofence.not_set') }}</span>
            @endif
        </p>
    </fieldset>

    {{-- Molduras: módulo 8. --}}
</div>

@unless ($event->exists)
    <div class="mt-6 rounded-xl border border-line p-4">
        <label class="choice items-start">
            <input type="checkbox" name="image_authorization" value="1" class="checkbox mt-0.5" required @checked(old('image_authorization'))>
            <span>{{ __('app.events.image_authorization') }}</span>
        </label>
        <p class="mt-2 text-xs text-muted">{{ __('app.events.image_authorization_hint') }}</p>
        <x-input-error name="image_authorization" />
    </div>
@endunless
