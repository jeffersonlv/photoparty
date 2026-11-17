@php($user = auth()->user())
<x-layouts.app :title="$event->name">
    <a href="{{ route('events.index') }}" class="mb-3 inline-flex min-h-touch items-center gap-1 text-sm font-bold text-brand-700 hover:text-brand-900">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4l-6 6 6 6"/></svg>
        {{ __('app.nav.back_to_events') }}
    </a>

    @if ($event->isDraft())
        <div role="status" class="alert alert-warn mb-5 items-start">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 2.8a1.7 1.7 0 013 0l6.2 11.2A1.7 1.7 0 0116.2 16.5H3.8A1.7 1.7 0 012.3 14L8.5 2.8zM10 7a.9.9 0 00-.9.9v3a.9.9 0 001.8 0v-3A.9.9 0 0010 7zm0 7.6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <div>
                <p class="font-bold">{{ __('app.events.draft_notice_title') }}</p>
                <p class="mt-1">{{ __('app.events.draft_notice_text') }}</p>
            </div>
        </div>
    @endif

    <div class="mb-5">
        <h1 class="page-title">{{ $event->name }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-2">
            @include('events._status', ['status' => $event->status])
            @if ($event->is_trial)
                <span class="badge bg-flash-soft text-[#5c4300]">{{ __('app.events.trial_badge') }}</span>
            @endif
        </p>
    </div>

    {{-- Ações do evento: um único bloco. Principal em largura total; as demais em 2 colunas no celular. --}}
    <div class="mb-6 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap" data-event-actions>
        @if ($event->isDraft() && $user->can('publish', $event) && $account->canManageEvents())
            <x-confirm-form :action="route('events.publish', $event)" :confirm="__('app.events.publish_confirm')" class="col-span-2 sm:col-auto">
                <x-button variant="flash" class="btn-block">{{ __('app.events.publish') }}</x-button>
            </x-confirm-form>
        @endif

        @can('manageTokens', $event)
            @unless ($event->isDraft())
                <a href="{{ route('screen.show', ['token' => $event->screenToken()], false) }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary col-span-2 sm:col-auto">{{ __('app.events.open_screen') }}</a>
            @endunless
        @endcan

        @can('moderate', $event)
            <a href="{{ route('events.moderation', $event) }}" class="btn btn-secondary">{{ __('app.events.moderation_link') }}
                <span class="badge bg-brand-100 text-brand-900">{{ $event->photos()->where('status', 'pending')->count() }}</span></a>
        @endcan

        @can('view', $event)
            <a href="{{ route('events.gallery', $event) }}" class="btn btn-secondary">{{ __('gallery.link') }}</a>
            <a href="{{ route('frames.index') }}" class="btn btn-secondary">{{ __('frames.nav') }}</a>
        @endcan

        @can('update', $event)
            <a href="{{ route('events.edit', $event) }}" class="btn btn-secondary">{{ __('app.events.edit') }}</a>
        @endcan

        @can('close', $event)
            <x-confirm-form :action="route('events.close', $event)" :confirm="__('app.events.close_confirm')">
                <x-button variant="secondary" class="btn-block">{{ __('app.events.close') }}</x-button>
            </x-confirm-form>
        @endcan

        @can('delete', $event)
            <x-confirm-form :action="route('events.destroy', $event)" method="DELETE" :confirm="__('app.events.delete_confirm')">
                <x-button variant="danger-outline" class="btn-block">{{ __('app.events.delete') }}</x-button>
            </x-confirm-form>
        @endcan
    </div>

    @if ($errors->any() && ! $errors->has('event'))
        <div class="alert alert-err mb-6" role="alert">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card mb-6">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-4">
            <div>
                <dt class="meta-label">{{ __('app.events.starts') }}</dt>
                <dd class="mt-0.5 font-bold">{{ $event->starts_at->format('d/m/Y') }}<span class="block text-sm font-normal text-muted">{{ $event->starts_at->format('H:i') }}</span></dd>
            </div>
            <div>
                <dt class="meta-label">{{ __('app.events.ends') }}</dt>
                <dd class="mt-0.5 font-bold">{{ $event->ends_at->format('d/m/Y') }}<span class="block text-sm font-normal text-muted">{{ $event->ends_at->format('H:i') }}</span></dd>
            </div>
            <div>
                <dt class="meta-label">{{ __('app.events.photo_limit') }}</dt>
                <dd class="mt-0.5 font-bold">{{ $event->photoLimit() }}</dd>
            </div>
            @if ($event->expires_at)
                <div>
                    <dt class="meta-label">{{ __('app.events.expires_at') }}</dt>
                    <dd class="mt-0.5 font-bold">{{ $event->expires_at->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>
        @if ($event->welcome_message)
            <p class="mt-5 whitespace-pre-line border-t border-line pt-4 text-sm text-muted">{{ $event->welcome_message }}</p>
        @endif
    </section>

    <div class="grid gap-6 md:grid-cols-2 md:items-start">
        <section class="card">
            <h2 class="section-title">{{ __('app.events.qr_title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('app.events.qr_text') }}</p>
            @if ($event->isDraft())
                <p class="alert alert-warn mt-3 py-2 font-bold">{{ __('app.events.draft_notice_title') }}</p>
            @endif
            <div class="mt-4 flex justify-center rounded-2xl bg-canvas p-4 ring-1 ring-line">
                <img src="{{ route('events.qr', [$event, 'svg']) }}" alt="" width="200" height="200" class="h-44 w-44 rounded-lg bg-white p-2 sm:h-48 sm:w-48">
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ route('events.qr', [$event, 'png']) }}" class="btn btn-primary">{{ __('app.events.qr_png') }}</a>
                <a href="{{ route('events.qr', [$event, 'svg']) }}" class="btn btn-secondary">{{ __('app.events.qr_svg') }}</a>
            </div>
            <x-copy-field class="mt-5" :value="$event->guestUrl()" :label="__('app.events.guest_link')" />
        </section>

        @can('manageTokens', $event)
            <section class="card">
                <h2 class="section-title">{{ __('app.events.screen_title') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ __('app.events.screen_text') }}</p>
                <x-copy-field class="mt-5" :value="$event->screenUrl()" :label="__('app.events.screen_link')" />
                <x-confirm-form :action="route('events.screen-token', $event)" :confirm="__('app.events.screen_regenerate_confirm')" class="mt-4">
                    <x-button variant="secondary" class="btn-block sm:w-auto">{{ __('app.events.screen_regenerate') }}</x-button>
                </x-confirm-form>
            </section>
        @endcan
    </div>
</x-layouts.app>
