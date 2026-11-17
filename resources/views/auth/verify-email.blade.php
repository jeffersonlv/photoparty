<x-layouts.guest :title="__('app.auth.verify_title')">
    <h1 class="page-title mb-2">{{ __('app.auth.verify_title') }}</h1>
    <p class="mb-6 text-sm text-muted">{{ __('app.auth.verify_text') }}</p>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>{{ __('app.auth.verify_resend') }}</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link inline-flex min-h-touch items-center text-sm">{{ __('app.nav.logout') }}</button>
        </form>
    </div>
</x-layouts.guest>
