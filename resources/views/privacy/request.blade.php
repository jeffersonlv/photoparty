{{-- Formulário público de solicitação do titular (LGPD/GDPR). Sem script inline. --}}
<x-home.layout :title="__('privacy.form.title').' · '.__('app.name')" :index="false">
    <article class="hm-wrap hm-legal">
        <h1>{{ __('privacy.form.title') }}</h1>
        <p>{{ __('privacy.form.intro') }}</p>
        <p class="hm-footer__muted">{{ __('privacy.form.deadlines', ['lgpd' => $days['lgpd'], 'gdpr' => $days['gdpr']]) }}</p>

        @if (session('privacy_request_sent'))
            <div class="hm-legal__notice hm-legal__notice--ok" role="status">
                <p>{{ __('privacy.form.sent') }}</p>
                @if (session('privacy_request_protocol'))
                    <p>{{ __('privacy.form.protocol', ['protocol' => session('privacy_request_protocol')]) }}</p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="hm-legal__notice hm-legal__notice--err" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('privacy.request.store') }}" class="hm-form" novalidate>
            @csrf
            <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
            {{-- honeypot: invisível para pessoas --}}
            <div class="hm-hp" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <fieldset class="hm-form__field">
                <legend>{{ __('privacy.form.type') }}</legend>
                @foreach ($types as $type)
                    <label class="hm-form__choice">
                        <input type="radio" name="type" value="{{ $type }}" @checked(old('type', 'deletion') === $type) required>
                        {{ __('privacy.types.'.$type) }}
                    </label>
                @endforeach
            </fieldset>

            <div class="hm-form__field">
                <label for="event_link">{{ __('privacy.form.event_link') }}</label>
                <input id="event_link" name="event_link" type="text" maxlength="500" required value="{{ old('event_link') }}" placeholder="https://photo.uailabs.net/e/...">
                <small>{{ __('privacy.form.event_link_hint') }}</small>
            </div>

            <div class="hm-form__field">
                <label for="email">{{ __('privacy.form.email') }}</label>
                <input id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="{{ old('email') }}">
                <small>{{ __('privacy.form.email_hint') }}</small>
            </div>

            <div class="hm-form__field">
                <label for="details">{{ __('privacy.form.details') }}</label>
                <textarea id="details" name="details" rows="4" maxlength="2000">{{ old('details') }}</textarea>
                <small>{{ __('privacy.form.details_hint') }}</small>
            </div>

            <label class="hm-form__choice">
                <input type="checkbox" name="confirm" value="1" required @checked(old('confirm'))>
                {{ __('privacy.form.confirm') }}
            </label>

            <button type="submit" class="hm-btn hm-btn--primary">{{ __('privacy.form.submit') }}</button>
        </form>

        <p><a class="hm-link" href="{{ route('legal.privacy') }}">{{ __('privacy.form.policy_link') }}</a></p>
    </article>
</x-home.layout>
