<x-layouts.guest :title="__('app.auth.reset_title')">
    <h1 class="page-title mb-6">{{ __('app.auth.reset_title') }}</h1>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-label for="email">{{ ucfirst(__('app.fields.email')) }}</x-label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="input">
            <x-input-error name="email" />
        </div>

        <div>
            <x-label for="password">{{ ucfirst(__('app.fields.password')) }}</x-label>
            <x-password-input name="password" autocomplete="new-password" />
            <p class="mt-1 text-xs text-muted">{{ __('app.auth.password_hint') }}</p>
            <x-input-error name="password" />
        </div>

        <div>
            <x-label for="password_confirmation">{{ ucfirst(__('app.fields.password_confirmation')) }}</x-label>
            <x-password-input name="password_confirmation" autocomplete="new-password" />
        </div>

        <x-button class="btn-block mt-2">{{ __('app.auth.reset_button') }}</x-button>
    </form>
</x-layouts.guest>
