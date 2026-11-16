<span @class([
    'rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-slate-100 text-slate-700' => $status === \App\Enums\EventStatus::Draft,
    'bg-sky-100 text-sky-800' => $status === \App\Enums\EventStatus::Scheduled,
    'bg-emerald-100 text-emerald-800' => $status === \App\Enums\EventStatus::Live,
    'bg-zinc-200 text-zinc-700' => in_array($status, [\App\Enums\EventStatus::Ended, \App\Enums\EventStatus::Expired], true),
])>{{ $status->label() }}</span>
