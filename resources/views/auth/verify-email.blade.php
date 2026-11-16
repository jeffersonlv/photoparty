<x-layouts.guest :title="__('app.auth.verify_title')">
    <h1 class="mb-2 text-xl font-semibold">{{ __('app.auth.verify_title') }}</h1>
    <p class="mb-6 text-sm text-slate-600">{{ __('app.auth.verify_text') }}</p>

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>{{ __('app.auth.verify_resend') }}</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link text-sm">{{ __('app.nav.logout') }}</button>
        </form>
    </div>
</x-layouts.guest>
