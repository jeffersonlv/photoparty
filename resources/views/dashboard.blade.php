<x-layouts.app :title="__('app.dashboard.title')">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">{{ __('app.dashboard.title') }}</h1>
        @if ($account->canManageEvents() && auth()->user()->isOwner())
            <a href="{{ route('events.create') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('app.events.new') }}</a>
        @endif
    </div>

    <section class="card mb-8">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.dashboard.account') }}</dt>
                <dd class="font-medium">{{ $account->name }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.dashboard.status') }}</dt>
                <dd class="font-medium">{{ $account->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase text-slate-500">{{ __('app.dashboard.plan') }}</dt>
                <dd class="font-medium">{{ $account->plan?->name ?? __('app.dashboard.no_plan') }}</dd>
            </div>
        </dl>

        @if ($account->hasTrialAvailable())
            <p class="mt-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">{{ __('app.dashboard.trial_available') }}</p>
        @elseif ($account->trialWindowOpen())
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('app.dashboard.trial_running', ['time' => $account->trial_ends_at->format('d/m/Y H:i')]) }}</p>
        @elseif ($account->status === \App\Enums\AccountStatus::TrialEnded)
            <p class="mt-4 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">{{ __('app.dashboard.trial_ended') }}</p>
        @endif
    </section>

    @foreach (['live' => $live, 'upcoming' => $upcoming, 'past' => $past] as $group => $list)
        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold">{{ __('app.dashboard.'.$group) }}</h2>
            @include('events._list', ['events' => $list])
        </section>
    @endforeach
</x-layouts.app>
