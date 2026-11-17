@php($user = auth()->user())
@php($isOwner = $user->isOwner())
<x-layouts.app :title="__('frames.title')">
    <div class="mb-6">
        <h1 class="page-title">{{ __('frames.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('frames.intro') }}</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-err mb-6" role="alert">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($canUse)
        <div class="alert alert-info mb-6" role="note" data-frames-paid-only>
            <div>
                <p class="font-bold">{{ __('frames.paid_only') }}</p>
                <p class="mt-1">{{ __('frames.paid_only_hint') }}</p>
            </div>
        </div>
    @endunless

    {{-- Modelos prontos --}}
    <section class="mb-8" aria-labelledby="frames-templates">
        <h2 id="frames-templates" class="mb-3 text-lg font-bold">{{ __('frames.templates_title') }}</h2>
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($templates as $frame)
                @php($o = $frame->config['orientations']['portrait'] ?? null)
                <li class="card p-3 sm:p-3">
                    @if ($o)
                        @php($dir = '/frames/'.rawurlencode($frame->template_key).'/')
                        @include('frames._preview', [
                            'id' => 't-'.$loop->index,
                            'o' => $o,
                            'bg' => $o['background'] ? $dir.$o['background'] : null,
                            'overlay' => $o['overlay'] ? $dir.$o['overlay'] : null,
                            'label' => __('frames.preview_alt', ['name' => $frame->name]),
                        ])
                    @endif
                    <p class="mt-2 text-sm font-bold">{{ $frame->config['name'][str_starts_with(app()->getLocale(), 'en') ? 'en' : 'pt'] ?? $frame->name }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($canUse || $customs->isNotEmpty())
        {{-- Molduras personalizadas --}}
        <section class="mb-8" aria-labelledby="frames-custom">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="frames-custom" class="text-lg font-bold">{{ __('frames.custom_title') }}</h2>
                <span class="text-sm text-muted">{{ __('frames.custom_count', ['count' => $customs->count(), 'max' => $limit]) }}</span>
            </div>

            @if (! $isOwner)
                <p class="alert alert-info mb-4">{{ __('frames.moderator_notice') }}</p>
            @endif

            @if ($customs->isEmpty())
                <p class="card text-center text-sm text-muted">{{ __('frames.custom_empty') }}</p>
            @else
                <ul class="space-y-4">
                    @foreach ($customs as $frame)
                        <li class="card" data-custom-frame="{{ $frame->uuid }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold">{{ $frame->name }}</h3>
                                    @if (count($frame->orientations()) === 1)
                                        <p class="text-sm text-muted">{{ __('frames.only_orientation', ['orientation' => __('frames.orientation.'.$frame->orientations()[0])]) }}</p>
                                    @endif
                                    @unless ($frame->is_active)
                                        <p class="text-sm font-semibold text-red-700">{{ __('frames.disabled_by_admin') }}</p>
                                    @endunless
                                </div>
                                @can('delete', $frame)
                                    <x-confirm-form :action="route('frames.destroy', $frame)" method="DELETE" :confirm="__('frames.delete_confirm')">
                                        <x-button variant="danger-outline">{{ __('frames.delete') }}</x-button>
                                    </x-confirm-form>
                                @endcan
                            </div>

                            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                @foreach ($frame->orientations() as $orientation)
                                    <div @class(['sm:col-span-1' => $orientation === 'portrait', 'sm:col-span-2' => $orientation === 'landscape'])>
                                        @include('frames._preview', [
                                            'id' => 'c-'.$loop->parent->index.'-'.$orientation,
                                            'o' => $frame->config['orientations'][$orientation],
                                            'bg' => null,
                                            'overlay' => route('frames.image', ['frame' => $frame, 'orientation' => $orientation], false),
                                            'label' => __('frames.preview_alt', ['name' => $frame->name]).' ('.__('frames.orientation.'.$orientation).')',
                                        ])
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <h4 class="field-label">{{ __('frames.events_title') }}</h4>
                                @php($activeIds = $frame->events->pluck('uuid')->all())
                                @if ($events->isEmpty())
                                    <p class="mt-1 text-sm text-muted">{{ __('frames.events_empty') }}</p>
                                @elseif ($canUse && $isOwner)
                                    <form method="POST" action="{{ route('frames.events.update', $frame) }}" class="mt-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($events as $event)
                                                <label class="choice">
                                                    <input type="checkbox" class="checkbox" name="events[]" value="{{ $event->uuid }}" @checked(in_array($event->uuid, $activeIds, true))>
                                                    <span>{{ $event->name }} <span class="text-muted">· {{ $event->starts_at->format('d/m/Y H:i') }}</span></span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <x-button variant="secondary" class="mt-3">{{ __('frames.events_save') }}</x-button>
                                    </form>
                                @else
                                    <ul class="mt-1 list-inside list-disc text-sm">
                                        @forelse ($events->whereIn('uuid', $activeIds) as $event)
                                            <li>{{ $event->name }}</li>
                                        @empty
                                            <li class="text-muted">—</li>
                                        @endforelse
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($canUse && $isOwner)
        <section class="grid gap-6 lg:grid-cols-3" aria-labelledby="frames-upload">
            <form method="POST" action="{{ route('frames.store') }}" enctype="multipart/form-data" class="card space-y-4 lg:col-span-2">
                @csrf
                <h2 id="frames-upload" class="text-lg font-bold">{{ __('frames.upload_title') }}</h2>
                <p class="text-sm text-muted">{{ __('frames.upload_help', ['pw' => $sizes['portrait'][0], 'ph' => $sizes['portrait'][1], 'lw' => $sizes['landscape'][0], 'lh' => $sizes['landscape'][1]]) }}</p>

                <div>
                    <x-label for="frame-name">{{ __('frames.name') }}</x-label>
                    <input id="frame-name" name="name" type="text" class="input" maxlength="60" required value="{{ old('name') }}">
                    <x-input-error name="name" />
                </div>
                <div>
                    <x-label for="frame-portrait">{{ __('frames.portrait', ['w' => $sizes['portrait'][0], 'h' => $sizes['portrait'][1]]) }}</x-label>
                    <input id="frame-portrait" name="portrait" type="file" accept="image/png" class="input">
                    <x-input-error name="portrait" />
                </div>
                <div>
                    <x-label for="frame-landscape">{{ __('frames.landscape', ['w' => $sizes['landscape'][0], 'h' => $sizes['landscape'][1]]) }}</x-label>
                    <input id="frame-landscape" name="landscape" type="file" accept="image/png" class="input">
                    <x-input-error name="landscape" />
                </div>
                <x-button>{{ __('frames.submit') }}</x-button>
            </form>

            <aside class="card space-y-3">
                <h2 class="text-lg font-bold">{{ __('frames.guide_title') }}</h2>
                <p class="text-sm text-muted">{{ __('frames.guide_help') }}</p>
                <a class="btn btn-secondary btn-block" href="{{ route('frames.guide', ['orientation' => 'portrait']) }}" download>{{ __('frames.guide_portrait') }}</a>
                <a class="btn btn-secondary btn-block" href="{{ route('frames.guide', ['orientation' => 'landscape']) }}" download>{{ __('frames.guide_landscape') }}</a>
            </aside>
        </section>
    @endif
</x-layouts.app>
