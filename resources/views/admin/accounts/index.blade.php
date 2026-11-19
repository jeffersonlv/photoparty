<x-admin.layout :title="__('admin.accounts.title')">
    <h1>{{ __('admin.accounts.title') }}</h1>

    <form method="GET" class="adm-inline adm-search" role="search">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.accounts.search_placeholder') }}" maxlength="100" aria-label="{{ __('admin.common.search') }}">
        <button type="submit" class="adm-btn">{{ __('admin.common.search') }}</button>
        <a href="{{ route('admin.accounts.index') }}" class="adm-btn adm-btn--ghost">{{ __('admin.common.clear') }}</a>
    </form>

    <details class="adm-customize" @if ($errors->any()) open @endif>
        <summary class="adm-btn">{{ __('admin.accounts.customize') }}</summary>
        <form method="POST" action="{{ route('admin.accounts.settings') }}" class="adm-customize__body">
            @csrf
            @method('PUT')
            <p class="adm-muted">{{ __('admin.accounts.customize_hint') }}</p>
            <ol class="adm-customize__list">
                @foreach ($order as $i => $value)
                    @php($label = \App\Enums\AccountStatus::from($value)->label())
                    <li>
                        <input type="hidden" name="order[]" value="{{ $value }}">
                        <span class="adm-customize__name">{{ $label }}</span>
                        <label>
                            <span class="adm-muted">{{ __('admin.accounts.per_page') }}</span>
                            <select name="per_page[{{ $value }}]" aria-label="{{ __('admin.accounts.per_page') }}: {{ $label }}">
                                @foreach ($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($perPage[$value] === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                        <span class="adm-actions">
                            <button type="submit" class="adm-btn adm-btn--sm" name="move" value="{{ $value }}:up" @disabled($loop->first) aria-label="{{ __('admin.common.up') }}: {{ $label }}">&uarr;</button>
                            <button type="submit" class="adm-btn adm-btn--sm" name="move" value="{{ $value }}:down" @disabled($loop->last) aria-label="{{ __('admin.common.down') }}: {{ $label }}">&darr;</button>
                        </span>
                    </li>
                @endforeach
            </ol>
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
        </form>
    </details>

    @foreach ($order as $value)
        @include('admin.accounts._table', ['status' => \App\Enums\AccountStatus::from($value), 'paginator' => $sections[$value]])
    @endforeach
</x-admin.layout>
