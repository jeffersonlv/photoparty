@php($total = $paginator->total())
<details class="adm-status-section" id="status-{{ $status->value }}"@if ($total > 0) open @endif>
    <summary class="adm-status-section__title">
        <h2>{{ $status->label() }}</h2>
        <span class="adm-badge adm-badge--{{ $status->value }}">{{ $total }}</span>
    </summary>

    @if ($total === 0)
        <p class="adm-empty">{{ __('admin.accounts.no_accounts') }}</p>
    @else
        <div class="adm-table-wrap">
            <table class="adm-table adm-table--stack adm-table--accounts">
                <thead>
                    <tr>
                        <th>{{ __('admin.accounts.name') }}</th>
                        <th>{{ __('admin.accounts.plan') }}</th>
                        <th>{{ __('admin.accounts.expires_at') }}</th>
                        <th>{{ __('admin.accounts.events') }}</th>
                        <th>{{ __('admin.accounts.users') }}</th>
                        <th>{{ __('admin.accounts.created') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $account)
                        @php($expires = $account->expiresAt())
                        @php($remaining = $expires ? \App\Support\ExpiryLabel::remaining($expires) : null)
                        <tr>
                            <td data-label="{{ __('admin.accounts.name') }}"><a href="{{ route('admin.accounts.show', $account) }}">{{ $account->name }}</a></td>
                            <td data-label="{{ __('admin.accounts.plan') }}">{{ $account->plan?->displayName() ?? __('admin.accounts.no_plan') }}</td>
                            <td data-label="{{ __('admin.accounts.expires_at') }}" class="adm-expiry">
                                @if ($expires)
                                    <span class="adm-expiry__date">{{ \App\Support\ExpiryLabel::format($expires) }}</span>
                                    <small class="adm-expiry__left @if ($remaining['urgent']) adm-expiry__left--urgent @endif">{{ $remaining['text'] }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="{{ __('admin.accounts.events') }}">{{ $account->events_count }}</td>
                            <td data-label="{{ __('admin.accounts.users') }}">{{ $account->users_count }}</td>
                            <td data-label="{{ __('admin.accounts.created') }}">{{ $account->created_at?->format(app()->getLocale() === 'en' ? 'Y-m-d' : 'd/m/Y') }}</td>
                            <td class="adm-right"><a class="adm-btn adm-btn--sm" href="{{ route('admin.accounts.show', $account) }}">{{ __('admin.common.details') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $paginator->links('admin.pagination') }}
    @endif
</details>
