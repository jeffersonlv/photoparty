<x-admin.layout :title="__('admin.audit.title')">
    <h1>{{ __('admin.audit.title') }}</h1>

    @php($activeFilters = count(array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '')))
    <div class="adm-filterbox" x-data="disclosure">
        <button type="button" class="adm-btn adm-filterbox__toggle" aria-controls="adm-filters" aria-expanded="false" x-on:click="toggle" x-bind:aria-expanded="expanded">
            {{ __('app.nav.filters') }}@if ($activeFilters > 0) <span class="adm-badge adm-badge--trial">{{ $activeFilters }}</span>@endif
        </button>
    <form id="adm-filters" method="GET" class="adm-filters" x-bind:data-open="expanded">
        <input type="text" name="action" list="audit-actions" value="{{ $filters['action'] ?? '' }}" placeholder="{{ __('admin.audit.filter_action') }}" maxlength="80">
        <datalist id="audit-actions">
            @foreach ($actions as $action)
                <option value="{{ $action }}">
            @endforeach
        </datalist>
        <input type="text" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="{{ __('admin.audit.filter_user') }}" maxlength="120">
        <input type="text" name="account" value="{{ $filters['account'] ?? '' }}" placeholder="{{ __('admin.audit.filter_account') }}" maxlength="100">
        <label>{{ __('admin.audit.from') }} <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>{{ __('admin.audit.to') }} <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button type="submit" class="adm-btn">{{ __('admin.common.filter') }}</button>
        <a href="{{ route('admin.audit.index') }}" class="adm-btn adm-btn--ghost">{{ __('admin.common.clear') }}</a>
    </form>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table adm-table--stack">
            <thead>
                <tr>
                    <th>{{ __('admin.audit.when') }}</th>
                    <th>{{ __('admin.audit.user') }}</th>
                    <th>{{ __('admin.audit.account') }}</th>
                    <th>{{ __('admin.audit.action') }}</th>
                    <th>{{ __('admin.audit.subject') }}</th>
                    <th>{{ __('admin.audit.meta') }}</th>
                    <th>{{ __('admin.audit.ip') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="adm-nowrap" data-label="{{ __('admin.audit.when') }}">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td data-label="{{ __('admin.audit.user') }}">{{ $log->user?->email ?? __('admin.audit.system') }}</td>
                        <td data-label="{{ __('admin.audit.account') }}">{{ $log->account?->name ?? '—' }}</td>
                        <td data-label="{{ __('admin.audit.action') }}"><code>{{ $log->action }}</code></td>
                        <td data-label="{{ __('admin.audit.subject') }}">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td data-label="{{ __('admin.audit.meta') }}"><code class="adm-meta">{{ $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                        <td data-label="{{ __('admin.audit.ip') }}">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="adm-empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links('admin.pagination') }}
</x-admin.layout>
