<span @class([
    'badge',
    'bg-slate-100 text-slate-700' => $status === \App\Enums\EventStatus::Draft,
    'bg-sky-100 text-sky-900' => $status === \App\Enums\EventStatus::Scheduled,
    'bg-emerald-100 text-emerald-900' => $status === \App\Enums\EventStatus::Live,
    'bg-zinc-200 text-zinc-800' => in_array($status, [\App\Enums\EventStatus::Ended, \App\Enums\EventStatus::Expired], true),
])>
    @if ($status === \App\Enums\EventStatus::Live)
        <span class="live-dot h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
    @endif
    {{ $status->label() }}
</span>
