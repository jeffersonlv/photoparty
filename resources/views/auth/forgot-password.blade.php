<x-layouts.guest :title="__('app.auth.forgot_title')">
    <h1 class="mb-2 text-xl font-semibold">{{ __('app.auth.forgot_title') }}</h1>
    <p class="mb-6 text-sm text-slate-600">{{ __('app.auth.forgot_text') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="email">{{ ucfirst(__('app.fields.email')) }}</x-label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
            <x-input-error name="email" />
        </div>
        <x-button class="w-full">{{ __('app.auth.forgot_button') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="link">{{ __('app.auth.back_to_login') }}</a></p>
</x-layouts.guest>
