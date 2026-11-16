@if ($events->isEmpty())
    <p class="text-sm text-slate-500">{{ __('app.dashboard.none') }}</p>
@else
    <ul class="divide-y divide-slate-200 rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        @foreach ($events as $event)
            <li>
                <a href="{{ route('events.show', $event) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
                    <span class="font-medium">
                        {{ $event->name }}
                        @if ($event->is_trial)
                            <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{{ __('app.events.trial_badge') }}</span>
                        @endif
                    </span>
                    <span class="text-sm text-slate-500">
                        {{ $event->starts_at->format('d/m/Y H:i') }} – {{ $event->ends_at->format('H:i') }}
                        · @include('events._status', ['status' => $event->status])
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
