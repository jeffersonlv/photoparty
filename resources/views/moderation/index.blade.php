{{--
    Moderação ao vivo. Recebe $event e $config (listUrl, batchUrl, photoBase, pollMs, limit, defaultTab, moderation).
    A grade é desenhada por resources/js/moderation/index.js (textContent/atributos, nunca HTML cru).
--}}
@php
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $i18n = __('moderation');
@endphp
<x-layouts.app :title="__('moderation.title').' · '.$event->name">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('moderation.title') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $event->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('manageTokens', $event)
                @unless ($event->isDraft())
                    <a href="{{ route('screen.show', ['token' => $event->screenToken()], false) }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('app.events.open_screen') }}</a>
                @endunless
            @endcan
            <a href="{{ route('events.show', $event) }}" class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('moderation.back') }}</a>
        </div>
    </div>

    <p class="mb-4 text-sm text-slate-600">{{ $event->moderation === 'post' ? __('moderation.post_notice') : __('moderation.pre_notice') }}</p>

    <div id="mod-root" class="card" data-mod-root>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
            <div class="flex gap-1" role="tablist" data-mod-tabs>
                @foreach (['pending', 'approved', 'rejected'] as $tab)
                    <button type="button" role="tab" data-tab="{{ $tab }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 aria-selected:bg-indigo-600 aria-selected:text-white" aria-selected="false">
                        {{ __('moderation.tabs.'.$tab) }} <span data-count="{{ $tab }}" class="ml-1 rounded-full bg-slate-200 px-1.5 text-xs text-slate-700">0</span>
                    </button>
                @endforeach
            </div>
            <p class="text-xs text-slate-500"><span id="mod-error" class="text-red-600" hidden>{{ __('moderation.error') }}</span> <span id="mod-live">{{ __('moderation.updating') }}</span></p>
        </div>

        <div class="flex flex-wrap items-center gap-2 py-3" data-mod-toolbar>
            <button type="button" data-select-all class="rounded-lg bg-white px-3 py-1.5 text-sm text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('moderation.select_all') }}</button>
            <button type="button" data-clear class="rounded-lg bg-white px-3 py-1.5 text-sm text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('moderation.clear') }}</button>
            <span data-selected class="text-sm text-slate-600"></span>
            <span class="flex-1"></span>
            <button type="button" data-batch="approve" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40" disabled></button>
            <button type="button" data-batch="reject" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40" disabled></button>
        </div>

        <p id="mod-empty" class="py-10 text-center text-sm text-slate-500" hidden>{{ __('moderation.empty') }}</p>
        <ul id="mod-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"></ul>
        <p id="mod-more" class="mt-3 text-center text-xs text-slate-500" hidden></p>
    </div>

    @vite(['resources/js/moderation/index.js'])
    <script type="application/json" id="pp-moderation">@json($config, $jsonFlags)</script>
    <script type="application/json" id="pp-moderation-i18n">@json($i18n, $jsonFlags)</script>
</x-layouts.app>
