<x-layouts.app :title="__('app.events.title')" width="max-w-3xl">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">{{ __('app.events.title') }}</h1>
        @if ($account->canManageEvents() && auth()->user()->isOwner())
            <a href="{{ route('events.create') }}" class="btn btn-primary shrink-0">{{ __('app.events.new') }}</a>
        @endif
    </div>

    @if ($events->isEmpty())
        <p class="card text-center text-sm text-muted">{{ __('app.events.empty') }}</p>
    @else
        @include('events._list', ['events' => $events->getCollection()])
        <div class="mt-6">{{ $events->links() }}</div>
    @endif
</x-layouts.app>
