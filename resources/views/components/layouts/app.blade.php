@props(['title' => null])
<x-layouts.base :title="$title">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="text-lg font-bold">{{ __('app.name') }}</a>
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:text-slate-900">{{ __('app.nav.dashboard') }}</a>
                <a href="{{ route('events.index') }}" class="text-sm text-slate-600 hover:text-slate-900">{{ __('app.nav.events') }}</a>
            </div>
            <div class="flex items-center gap-4">
                <x-locale-switcher />
                <span class="hidden text-sm text-slate-500 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">{{ __('app.nav.logout') }}</button>
                </form>
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>
</x-layouts.base>
