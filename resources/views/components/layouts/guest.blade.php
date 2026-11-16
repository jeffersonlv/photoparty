@props(['title' => null])
<x-layouts.base :title="$title">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 text-center text-2xl font-bold tracking-tight">{{ __('app.name') }}</a>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <x-flash />
            {{ $slot }}
        </div>
        <x-locale-switcher class="mt-6 justify-center" />
    </main>
</x-layouts.base>
