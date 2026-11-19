<x-admin.layout :title="__('admin.plans.title')">
    <div class="adm-head">
        <h1>{{ __('admin.plans.title') }}</h1>
        <a class="adm-btn adm-btn--primary" href="{{ route('admin.plans.create') }}">{{ __('admin.plans.new') }}</a>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table adm-table--stack">
            <thead>
                <tr>
                    <th>{{ __('admin.plans.sort_order') }}</th>
                    <th>{{ __('admin.plans.name') }}</th>
                    <th>{{ __('admin.plans.price') }}</th>
                    <th>{{ __('admin.plans.max_events') }}</th>
                    <th>{{ __('admin.plans.max_photos') }}</th>
                    <th>{{ __('admin.plans.retention') }}</th>
                    <th>{{ __('admin.plans.is_public') }}</th>
                    <th>{{ __('admin.plans.accounts') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td data-label="{{ __('admin.plans.sort_order') }}">{{ $plan->sort_order }}</td>
                        <td data-label="{{ __('admin.plans.name') }}">{{ $plan->displayName() }} <small class="adm-muted">{{ $plan->slug }}</small></td>
                        <td data-label="{{ __('admin.plans.price') }}">{{ $plan->currency }} {{ number_format((float) $plan->price, 2, ',', '.') }}</td>
                        <td data-label="{{ __('admin.plans.max_events') }}">{{ $plan->max_events_per_month ?? __('admin.plans.unlimited') }}</td>
                        <td data-label="{{ __('admin.plans.max_photos') }}">{{ $plan->max_photos_per_event }}</td>
                        <td data-label="{{ __('admin.plans.retention') }}">{{ $plan->retention_days }}</td>
                        <td data-label="{{ __('admin.plans.is_public') }}">{{ $plan->is_public ? __('admin.common.yes') : __('admin.common.no') }}</td>
                        <td data-label="{{ __('admin.plans.accounts') }}">{{ $plan->accounts_count }}</td>
                        <td class="adm-right adm-actions">
                            <a class="adm-btn adm-btn--sm" href="{{ route('admin.plans.edit', $plan) }}">{{ __('admin.common.edit') }}</a>
                            <x-admin.delete :action="route('admin.plans.destroy', $plan)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="adm-empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
