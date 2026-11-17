{{--
    Moderação ao vivo. Recebe $event e $config (listUrl, batchUrl, photoBase, pollMs, limit, defaultTab, moderation).
    A grade é desenhada por resources/js/moderation/index.js (textContent/atributos, nunca HTML cru).
--}}
@php
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $i18n = __('moderation');
@endphp
<x-layouts.app :title="__('moderation.title').' · '.$event->name">
    <a href="{{ route('events.show', $event) }}" class="mb-3 inline-flex min-h-touch items-center gap-1 text-sm font-bold text-brand-700 hover:text-brand-900">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4l-6 6 6 6"/></svg>
        {{ __('moderation.back') }}
    </a>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">{{ __('moderation.title') }}</h1>
            <p class="mt-1 truncate text-sm text-muted">{{ $event->name }}</p>
        </div>
        @can('manageTokens', $event)
            @unless ($event->isDraft())
                <a href="{{ route('screen.show', ['token' => $event->screenToken()], false) }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">{{ __('app.events.open_screen') }}</a>
            @endunless
        @endcan
    </div>

    <p class="alert alert-info mb-4" role="note">{{ $event->moderation === 'post' ? __('moderation.post_notice') : __('moderation.pre_notice') }}</p>

    <div id="mod-root" class="card -mx-4 rounded-none p-4 ring-0 sm:mx-0 sm:rounded-2xl sm:ring-1" data-mod-root>
        <div class="border-b border-line pb-3">
            <div class="grid grid-cols-3 gap-1.5 rounded-xl bg-canvas p-1" role="tablist" data-mod-tabs>
                @foreach (['pending', 'approved', 'rejected'] as $tab)
                    <button type="button" role="tab" data-tab="{{ $tab }}" class="flex min-h-touch items-center justify-center gap-1.5 rounded-lg px-2 text-sm font-bold text-muted hover:bg-white hover:text-ink aria-selected:bg-ink aria-selected:text-white" aria-selected="false">
                        {{ __('moderation.tabs.'.$tab) }} <span data-count="{{ $tab }}" class="rounded-full bg-slate-200 px-1.5 text-xs text-slate-800">0</span>
                    </button>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-muted"><span id="mod-error" class="font-bold text-red-700" hidden>{{ __('moderation.error') }}</span> <span id="mod-live">{{ __('moderation.updating') }}</span></p>
        </div>

        <div class="flex flex-wrap items-center gap-2 py-3" data-mod-toolbar>
            <button type="button" data-select-all class="btn btn-secondary min-h-[2.5rem] px-3">{{ __('moderation.select_all') }}</button>
            <button type="button" data-clear class="btn btn-secondary min-h-[2.5rem] px-3">{{ __('moderation.clear') }}</button>
            <span data-selected class="text-sm font-bold text-brand-800"></span>
            <span class="hidden md:block md:flex-1" aria-hidden="true"></span>
            <button type="button" data-batch="approve" class="btn btn-primary bg-emerald-700 hover:bg-emerald-800 min-h-[2.5rem]" disabled></button>
            <button type="button" data-batch="reject" class="btn btn-danger min-h-[2.5rem]" disabled></button>
        </div>

        <p id="mod-empty" class="py-10 text-center text-sm text-muted" hidden>{{ __('moderation.empty') }}</p>
        <ul id="mod-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"></ul>
        <p id="mod-more" class="mt-3 text-center text-xs text-muted" hidden></p>
    </div>

    @vite(['resources/js/moderation/index.js'])
    <script type="application/json" id="pp-moderation">@json($config, $jsonFlags)</script>
    <script type="application/json" id="pp-moderation-i18n">@json($i18n, $jsonFlags)</script>
</x-layouts.app>
