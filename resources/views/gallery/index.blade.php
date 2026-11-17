{{-- Galeria do evento. Sem JS: miniaturas via media.show (a regra de plano é aplicada lá). --}}
<x-layouts.app :title="__('gallery.title').' · '.$event->name">
    <a href="{{ route('events.show', $event) }}" class="mb-3 inline-flex min-h-touch items-center gap-1 text-sm font-bold text-brand-700 hover:text-brand-900">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4l-6 6 6 6"/></svg>
        {{ __('gallery.back') }}
    </a>

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">{{ __('gallery.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $event->name }} · {{ __('gallery.count', ['count' => $approvedCount]) }}</p>
        </div>
        @if ($canDownload && $approvedCount > 0)
            <form method="POST" action="{{ route('events.exports.store', $event) }}" class="w-full sm:w-auto">
                @csrf
                <x-button class="btn-block">{{ __('gallery.download_all') }}</x-button>
            </form>
        @endif
    </div>

    @unless ($canDownload)
        <div role="status" class="alert alert-warn mb-6">{{ __('gallery.preview_notice') }}</div>
    @endunless

    @if ($export)
        <section class="card mb-6 text-sm">
            <h2 class="section-title">{{ __('gallery.export_title') }}</h2>
            <p class="mt-1 text-muted">
                {{ __('gallery.export_status.'.$export->status) }}
                @if ($export->status === 'ready' && $export->expires_at)
                    · {{ __('gallery.export_expires', ['date' => $export->expires_at->format('d/m/Y H:i')]) }}
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($export->status === 'ready')
                    <a class="btn btn-primary" href="{{ route('events.exports.download', [$event, $export]) }}">{{ __('gallery.export_download') }}</a>
                @endif
                @if ($exportInProgress)
                    <a class="btn btn-secondary" href="{{ route('events.gallery', $event) }}">{{ __('gallery.refresh') }}</a>
                @endif
            </div>
        </section>
    @endif

    @if ($photos->isEmpty())
        <p class="card text-center text-sm text-muted">{{ __('gallery.empty') }}</p>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($photos as $photo)
                <li class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-line">
                    <div class="relative overflow-hidden">
                    <img src="{{ $photo->mediaUrl('thumb') }}" alt="{{ __('gallery.photo_alt', ['number' => $firstNumber + $loop->index]) }}" loading="lazy" class="aspect-[4/5] w-full bg-slate-200 object-cover">
                    @unless ($canDownload)
                        <span class="pointer-events-none absolute inset-x-0 top-1/2 -translate-y-1/2 -rotate-[30deg] bg-black/40 py-1 text-center text-xs font-extrabold tracking-widest text-white">{{ __('gallery.demo') }}</span>
                    @endunless
                    </div>
                    @if ($canDownload)
                        <div class="p-1">
                            <a class="flex min-h-touch items-center justify-center rounded-lg text-sm font-bold text-brand-700 hover:bg-brand-50" href="{{ route('events.photos.download', [$event, $photo]) }}">{{ __('gallery.download') }}</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $photos->links() }}</div>
    @endif
</x-layouts.app>
