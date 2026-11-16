<x-admin.layout :title="__('admin.accounts.title')">
    <h1>{{ __('admin.accounts.title') }}</h1>

    <form method="GET" class="adm-filters">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.accounts.search_placeholder') }}" maxlength="100" aria-label="{{ __('admin.common.search') }}">
        <select name="status" aria-label="{{ __('admin.accounts.status') }}">
            <option value="">{{ __('admin.accounts.all_statuses') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="adm-btn">{{ __('admin.common.filter') }}</button>
        <a href="{{ route('admin.accounts.index') }}" class="adm-btn adm-btn--ghost">{{ __('admin.common.clear') }}</a>
    </form>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>{{ __('admin.accounts.name') }}</th>
                    <th>{{ __('admin.accounts.status') }}</th>
                    <th>{{ __('admin.accounts.plan') }}</th>
                    <th>{{ __('admin.accounts.events') }}</th>
                    <th>{{ __('admin.accounts.users') }}</th>
                    <th>{{ __('admin.accounts.created') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td><a href="{{ route('admin.accounts.show', $account) }}">{{ $account->name }}</a></td>
                        <td><span class="adm-badge adm-badge--{{ $account->status->value }}">{{ $account->status->label() }}</span></td>
                        <td>{{ $account->plan?->name ?? __('admin.accounts.no_plan') }}</td>
                        <td>{{ $account->events_count }}</td>
                        <td>{{ $account->users_count }}</td>
                        <td>{{ $account->created_at?->format('d/m/Y') }}</td>
                        <td class="adm-right"><a class="adm-btn adm-btn--sm" href="{{ route('admin.accounts.show', $account) }}">{{ __('admin.common.details') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="adm-empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $accounts->links('admin.pagination') }}
</x-admin.layout>
