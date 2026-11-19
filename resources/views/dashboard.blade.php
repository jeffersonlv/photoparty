<x-layouts.app :title="__('app.dashboard.title')" width="max-w-3xl">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">{{ __('app.dashboard.title') }}</h1>
        @if ($account->canManageEvents() && auth()->user()->isOwner())
            <a href="{{ route('events.create') }}" class="btn btn-primary shrink-0">{{ __('app.events.new') }}</a>
        @endif
    </div>

    <section class="mb-8 overflow-hidden rounded-2xl bg-ink text-white shadow-card">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-4 p-5 sm:grid-cols-3">
            <div class="col-span-2 sm:col-span-1">
                <dt class="text-xs font-semibold text-white/70">{{ __('app.dashboard.account') }}</dt>
                <dd class="mt-0.5 truncate text-lg font-bold">{{ $account->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-white/70">{{ __('app.dashboard.status') }}</dt>
                <dd class="mt-0.5 font-bold">{{ $account->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-white/70">{{ __('app.dashboard.plan') }}</dt>
                <dd class="mt-0.5 font-bold">{{ $account->plan?->displayName() ?? __('app.dashboard.no_plan') }}</dd>
            </div>
        </dl>

        @if ($account->hasTrialAvailable())
            <p class="border-t border-white/10 bg-brand-600 px-5 py-3 text-sm font-semibold">{{ __('app.dashboard.trial_available') }}</p>
        @elseif ($account->trialWindowOpen())
            <p class="border-t border-white/10 bg-flash px-5 py-3 text-sm font-semibold text-ink">{{ __('app.dashboard.trial_running', ['time' => $account->trial_ends_at->format('d/m/Y H:i')]) }}</p>
        @elseif ($account->status === \App\Enums\AccountStatus::TrialEnded)
            <p class="border-t border-white/10 bg-white/10 px-5 py-3 text-sm">{{ __('app.dashboard.trial_ended') }}</p>
        @endif
    </section>

    @foreach (['live' => $live, 'upcoming' => $upcoming, 'past' => $past] as $group => $list)
        <section class="mb-8">
            <h2 class="section-title mb-3">{{ __('app.dashboard.'.$group) }}</h2>
            @include('events._list', ['events' => $list])
        </section>
    @endforeach
</x-layouts.app>
