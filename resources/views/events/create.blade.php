<x-layouts.app :title="__('app.events.create_title')" width="max-w-2xl">
    <h1 class="page-title mb-6">{{ __('app.events.create_title') }}</h1>

    <form method="POST" action="{{ route('events.store') }}" class="card">
        @csrf
        @include('events._form')
        <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse sm:justify-start">
            <x-button class="sm:min-w-[10rem]">{{ __('app.events.save') }}</x-button>
            <a href="{{ route('events.index') }}" class="btn btn-secondary">{{ __('app.events.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
