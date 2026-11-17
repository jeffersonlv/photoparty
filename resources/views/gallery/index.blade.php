{{-- Galeria do evento. Sem JS: miniaturas via media.show (a regra de plano é aplicada lá). --}}
<x-layouts.app :title="__('gallery.title').' · '.$event->name">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('gallery.title') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $event->name }} · {{ __('gallery.count', ['count' => $approvedCount]) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canDownload && $approvedCount > 0)
                <form method="POST" action="{{ route('events.exports.store', $event) }}">
                    @csrf
                    <x-button>{{ __('gallery.download_all') }}</x-button>
                </form>
            @endif
            <a href="{{ route('events.show', $event) }}" class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ __('gallery.back') }}</a>
        </div>
    </div>

    @unless ($canDownload)
        <div role="status" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('gallery.preview_notice') }}</div>
    @endunless

    @if ($export)
        <section class="card mb-6 text-sm">
            <h2 class="font-semibold">{{ __('gallery.export_title') }}</h2>
            <p class="mt-1">
                {{ __('gallery.export_status.'.$export->status) }}
                @if ($export->status === 'ready' && $export->expires_at)
                    · {{ __('gallery.export_expires', ['date' => $export->expires_at->format('d/m/Y H:i')]) }}
                @endif
            </p>
            <div class="mt-2 flex flex-wrap gap-3">
                @if ($export->status === 'ready')
                    <a class="font-semibold text-indigo-700 underline" href="{{ route('events.exports.download', [$event, $export]) }}">{{ __('gallery.export_download') }}</a>
                @endif
                @if ($exportInProgress)
                    <a class="text-indigo-700 underline" href="{{ route('events.gallery', $event) }}">{{ __('gallery.refresh') }}</a>
                @endif
            </div>
        </section>
    @endif

    @if ($photos->isEmpty())
        <p class="card text-sm text-slate-600">{{ __('gallery.empty') }}</p>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($photos as $photo)
                <li class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200">
                    <div class="relative overflow-hidden">
                    <img src="{{ $photo->mediaUrl('thumb') }}" alt="{{ __('gallery.photo_alt', ['number' => $firstNumber + $loop->index]) }}" loading="lazy" class="aspect-[4/5] w-full bg-slate-200 object-cover">
                    @unless ($canDownload)
                        <span class="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 -rotate-[30deg] bg-black/40 py-1 text-center text-xs font-extrabold tracking-widest text-white">{{ __('gallery.demo') }}</span>
                    @endunless
                    </div>
                    @if ($canDownload)
                        <div class="p-2 text-center">
                            <a class="text-xs font-semibold text-indigo-700 underline" href="{{ route('events.photos.download', [$event, $photo]) }}">{{ __('gallery.download') }}</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $photos->links() }}</div>
    @endif
</x-layouts.app>
