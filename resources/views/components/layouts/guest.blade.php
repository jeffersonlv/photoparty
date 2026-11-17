@props(['title' => null])
<x-layouts.base :title="$title">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 pb-10 pt-8 sm:justify-center">
        <a href="{{ route('home') }}" class="mx-auto mb-6 flex min-h-touch items-center gap-3 rounded-lg">
            <x-logo size="h-10 w-10" />
            <span class="font-display text-3xl tracking-wide">{{ __('app.name') }}</span>
        </a>
        <div class="card sm:p-8">
            <x-flash />
            {{ $slot }}
        </div>
        <x-locale-switcher class="mt-6 justify-center gap-2 text-sm [&_a]:inline-flex [&_a]:min-h-touch [&_a]:min-w-touch [&_a]:items-center [&_a]:justify-center [&_a]:rounded-lg" />
    </main>
</x-layouts.base>
