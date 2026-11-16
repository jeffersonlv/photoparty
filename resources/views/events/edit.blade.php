<x-layouts.app :title="__('app.events.edit_title')">
    <h1 class="mb-6 text-2xl font-bold">{{ __('app.events.edit_title') }}</h1>

    <form method="POST" action="{{ route('events.update', $event) }}" class="card">
        @csrf
        @method('PUT')
        @include('events._form')
        <div class="mt-8 flex gap-3">
            <x-button>{{ __('app.events.save') }}</x-button>
            <a href="{{ route('events.show', $event) }}" class="inline-flex items-center px-4 py-2 text-sm text-slate-600">{{ __('app.events.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
