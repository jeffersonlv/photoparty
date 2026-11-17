@if ($events->isEmpty())
    <p class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-muted">{{ __('app.dashboard.none') }}</p>
@else
    <ul class="space-y-3">
        @foreach ($events as $event)
            <li>
                <a href="{{ route('events.show', $event) }}"
                   class="group flex min-h-[4.5rem] items-center gap-4 rounded-2xl bg-white p-3 shadow-card ring-1 ring-line transition hover:ring-brand-400 sm:p-4">
                    <span class="stub" aria-hidden="true">
                        <span class="stub-day">{{ $event->starts_at->format('d') }}</span>
                        <span class="stub-month">{{ $event->starts_at->translatedFormat('M') }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-base font-bold">{{ $event->name }}</span>
                        <span class="mt-0.5 block text-sm text-muted">
                            {{ $event->starts_at->format('d/m/Y H:i') }} – {{ $event->ends_at->format('H:i') }}
                        </span>
                        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @include('events._status', ['status' => $event->status])
                            @if ($event->is_trial)
                                <span class="badge bg-flash-soft text-[#5c4300]">{{ __('app.events.trial_badge') }}</span>
                            @endif
                        </span>
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4l6 6-6 6"/></svg>
                </a>
            </li>
        @endforeach
    </ul>
@endif
