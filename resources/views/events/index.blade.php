<x-layouts.app :title="__('app.events.title')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">{{ __('app.events.title') }}</h1>
        @if ($account->canManageEvents() && auth()->user()->isOwner())
            <a href="{{ route('events.create') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('app.events.new') }}</a>
        @endif
    </div>

    @if ($events->isEmpty())
        <p class="card text-sm text-slate-600">{{ __('app.events.empty') }}</p>
    @else
        @include('events._list', ['events' => $events->getCollection()])
        <div class="mt-6">{{ $events->links() }}</div>
    @endif
</x-layouts.app>
