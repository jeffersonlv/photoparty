<x-layouts.guest :title="__('app.auth.register_title')">
    <h1 class="text-xl font-semibold">{{ __('app.auth.register_title') }}</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">{{ __('app.auth.register_subtitle') }}</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        {{-- Honeypot: humanos não veem; se vier preenchido, o cadastro é recusado. --}}
        <div class="hp-field" aria-hidden="true">
            <label for="website">{{ __('app.auth.honeypot_label') }}</label>
            <input id="website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <x-label for="name">{{ ucfirst(__('app.fields.name')) }}</x-label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="120" class="input">
            <x-input-error name="name" />
        </div>

        <div>
            <x-label for="email">{{ ucfirst(__('app.fields.email')) }}</x-label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" maxlength="190" class="input">
            <x-input-error name="email" />
        </div>

        <div>
            <x-label for="password">{{ ucfirst(__('app.fields.password')) }}</x-label>
            <x-password-input name="password" autocomplete="new-password" />
            <p class="mt-1 text-xs text-slate-500">{{ __('app.auth.password_hint') }}</p>
            <x-input-error name="password" />
        </div>

        <div>
            <x-label for="password_confirmation">{{ ucfirst(__('app.fields.password_confirmation')) }}</x-label>
            <x-password-input name="password_confirmation" autocomplete="new-password" />
        </div>

        <div>
            <label class="flex items-start gap-2 text-sm text-slate-600">
                <input type="checkbox" name="terms" value="1" required class="checkbox mt-0.5" @checked(old('terms'))>
                <span>{{ __('app.auth.terms_label') }}</span>
            </label>
            <x-input-error name="terms" />
            <x-input-error name="website" />
        </div>

        <x-button class="w-full">{{ __('app.auth.register_button') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm">
        {{ __('app.auth.have_account') }} <a href="{{ route('login') }}" class="link">{{ __('app.nav.login') }}</a>
    </p>
</x-layouts.guest>
