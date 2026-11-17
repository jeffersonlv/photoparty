@php($user = auth()->user())
<x-layouts.app :title="$event->name">
    @if ($event->isDraft())
        <div role="status" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            <p class="font-semibold">{{ __('app.events.draft_notice_title') }}</p>
            <p class="mt-1 text-sm">{{ __('app.events.draft_notice_text') }}</p>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $event->name }}
                @if ($event->is_trial)
                    <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 align-middle text-xs text-amber-800">{{ __('app.events.trial_badge') }}</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-600">@include('events._status', ['status' => $event->status])</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('moderate', $event)
                <a href="{{ route('events.moderation', $event) }}" class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('app.events.moderation_link') }} ({{ $event->photos()->where('status', 'pending')->count() }})</a>
            @endcan

            @can('manageTokens', $event)
                @unless ($event->isDraft())
                    <a href="{{ route('screen.show', ['token' => $event->screenToken()], false) }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('app.events.open_screen') }}</a>
                @endunless
            @endcan

            @can('update', $event)
                <a href="{{ route('events.edit', $event) }}" class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('app.events.edit') }}</a>
            @endcan

            @if ($event->isDraft() && $user->can('publish', $event) && $account->canManageEvents())
                <x-confirm-form :action="route('events.publish', $event)" :confirm="__('app.events.publish_confirm')">
                    <x-button>{{ __('app.events.publish') }}</x-button>
                </x-confirm-form>
            @endif

            @can('close', $event)
                <x-confirm-form :action="route('events.close', $event)" :confirm="__('app.events.close_confirm')">
                    <x-button variant="secondary">{{ __('app.events.close') }}</x-button>
                </x-confirm-form>
            @endcan

            @can('delete', $event)
                <x-confirm-form :action="route('events.destroy', $event)" method="DELETE" :confirm="__('app.events.delete_confirm')">
                    <x-button variant="danger">{{ __('app.events.delete') }}</x-button>
                </x-confirm-form>
            @endcan
        </div>
    </div>

    @if ($errors->any() && ! $errors->has('event'))
        <div class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card mb-6">
        <dl class="grid gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.events.starts') }}</dt>
                <dd>{{ $event->starts_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.events.ends') }}</dt>
                <dd>{{ $event->ends_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.events.photo_limit') }}</dt>
                <dd>{{ $event->photoLimit() }}</dd>
            </div>
            @if ($event->expires_at)
                <div>
                    <dt class="text-xs uppercase text-slate-500">{{ __('app.events.expires_at') }}</dt>
                    <dd>{{ $event->expires_at->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>
        @if ($event->welcome_message)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $event->welcome_message }}</p>
        @endif
    </section>

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <h2 class="text-lg font-semibold">{{ __('app.events.qr_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('app.events.qr_text') }}</p>
            @if ($event->isDraft())
                <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-sm font-medium text-amber-900">{{ __('app.events.draft_notice_title') }}</p>
            @endif
            <div class="mt-4 flex gap-2">
                <a href="{{ route('events.qr', [$event, 'png']) }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('app.events.qr_png') }}</a>
                <a href="{{ route('events.qr', [$event, 'svg']) }}" class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('app.events.qr_svg') }}</a>
            </div>
            <p class="mt-4 text-xs text-slate-500">{{ __('app.events.guest_link') }}:</p>
            <input type="text" readonly value="{{ $event->guestUrl() }}" class="input font-mono text-xs" aria-label="{{ __('app.events.guest_link') }}">
        </section>

        @can('manageTokens', $event)
            <section class="card">
                <h2 class="text-lg font-semibold">{{ __('app.events.screen_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('app.events.screen_text') }}</p>
                <p class="mt-4 text-xs text-slate-500">{{ __('app.events.screen_link') }}:</p>
                <input type="text" readonly value="{{ $event->screenUrl() }}" class="input font-mono text-xs" aria-label="{{ __('app.events.screen_link') }}">
                <x-confirm-form :action="route('events.screen-token', $event)" :confirm="__('app.events.screen_regenerate_confirm')" class="mt-4">
                    <x-button variant="secondary">{{ __('app.events.screen_regenerate') }}</x-button>
                </x-confirm-form>
            </section>
        @endcan
    </div>
</x-layouts.app>
