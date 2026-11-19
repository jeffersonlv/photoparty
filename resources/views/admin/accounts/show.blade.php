<x-admin.layout :title="$account->name">
    <p><a href="{{ route('admin.accounts.index') }}">&larr; {{ __('admin.accounts.title') }}</a></p>
    <h1>{{ $account->name }} <span class="adm-badge adm-badge--{{ $account->status->value }}">{{ $account->status->label() }}</span></h1>

    <section class="adm-grid">
        <div class="adm-card">
            <h2>{{ __('admin.accounts.usage') }}</h2>
            <dl class="adm-dl">
                <dt>{{ __('admin.accounts.events') }}</dt><dd>{{ $usage['events'] }}</dd>
                <dt>{{ __('admin.accounts.photos') }}</dt><dd>{{ $usage['photos'] }}</dd>
                <dt>{{ __('admin.accounts.storage') }}</dt><dd>{{ number_format($usage['bytes'] / 1048576, 1, ',', '.') }} MB</dd>
                <dt>{{ __('admin.accounts.plan') }}</dt><dd>{{ $account->plan?->displayName() ?? __('admin.accounts.no_plan') }}</dd>
                <dt>{{ __('admin.accounts.trial_used_at') }}</dt><dd>{{ $account->trial_used_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>{{ __('admin.accounts.trial_ends_at') }}</dt><dd>{{ $account->trial_ends_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>{{ __('admin.accounts.paid_until') }}</dt><dd>{{ $account->paid_until?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        </div>

        <div class="adm-card">
            <h2>{{ __('admin.accounts.change_plan') }}</h2>
            <form method="POST" action="{{ route('admin.accounts.plan', $account) }}" class="adm-inline">
                @csrf
                @method('PUT')
                <select name="plan_id" aria-label="{{ __('admin.accounts.plan') }}">
                    <option value="">{{ __('admin.accounts.no_plan') }}</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected($account->plan_id === $plan->id)>{{ $plan->displayName() }}</option>
                    @endforeach
                </select>
                <input type="date" name="paid_until" value="{{ old('paid_until', $account->paid_until?->format('Y-m-d')) }}" aria-label="{{ __('admin.accounts.paid_until') }}" title="{{ __('admin.accounts.paid_until') }}">
                <button type="submit" class="adm-btn">{{ __('admin.common.save') }}</button>
            </form>
            <p class="adm-muted">{{ __('admin.accounts.paid_until_hint') }}</p>

            <h2>{{ __('admin.accounts.paid_until') }}</h2>
            <form method="POST" action="{{ route('admin.accounts.paid-until', $account) }}" class="adm-inline">
                @csrf
                @method('PUT')
                <input type="date" name="paid_until" value="{{ old('paid_until', $account->paid_until?->format('Y-m-d')) }}" aria-label="{{ __('admin.accounts.paid_until') }}">
                <button type="submit" class="adm-btn">{{ __('admin.common.save') }}</button>
            </form>

            <h2>{{ __('admin.accounts.change_status') }}</h2>
            <form method="POST" action="{{ route('admin.accounts.status', $account) }}" class="adm-inline">
                @csrf
                @method('PUT')
                <select name="status" aria-label="{{ __('admin.accounts.status') }}">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($account->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button type="submit" class="adm-btn">{{ __('admin.common.save') }}</button>
            </form>

            <h2>{{ __('admin.accounts.extend_trial') }}</h2>
            <form method="POST" action="{{ route('admin.accounts.extend-trial', $account) }}" class="adm-inline">
                @csrf
                <input type="number" name="hours" min="1" max="720" value="{{ old('hours', 24) }}" aria-label="{{ __('admin.accounts.extend_hours') }}">
                <button type="submit" class="adm-btn">{{ __('admin.accounts.extend_button') }}</button>
            </form>
        </div>
    </section>

    <section class="adm-card">
        <h2>{{ __('admin.accounts.users_list') }}</h2>
        <ul class="adm-list">
            @foreach ($account->users as $user)
                <li>{{ $user->name }} &middot; {{ $user->email }} &middot; {{ $user->role->value }}</li>
            @endforeach
        </ul>
    </section>

    <section class="adm-card">
        <h2>{{ __('admin.accounts.recent_events') }}</h2>
        <ul class="adm-list">
            @forelse ($events as $event)
                <li>{{ $event->name }} &middot; {{ $event->status->label() }} &middot; {{ $event->starts_at?->format('d/m/Y H:i') }}</li>
            @empty
                <li class="adm-empty">{{ __('admin.common.empty') }}</li>
            @endforelse
        </ul>
    </section>
</x-admin.layout>
