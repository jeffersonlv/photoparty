<x-layouts.guest :title="__('app.auth.login_title')">
    <h1 class="mb-6 text-xl font-semibold">{{ __('app.auth.login_title') }}</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="email">{{ ucfirst(__('app.fields.email')) }}</x-label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            <x-input-error name="email" />
        </div>

        <div>
            <x-label for="password">{{ ucfirst(__('app.fields.password')) }}</x-label>
            <x-password-input name="password" autocomplete="current-password" />
            <x-input-error name="password" />
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="checkbox">
            {{ __('app.fields.remember') }}
        </label>

        <x-button class="w-full">{{ __('app.auth.login_button') }}</x-button>
    </form>

    <div class="mt-6 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="link">{{ __('app.auth.forgot_password') }}</a>
        <span>{{ __('app.auth.no_account') }} <a href="{{ route('register') }}" class="link">{{ __('app.nav.register') }}</a></span>
    </div>
</x-layouts.guest>
