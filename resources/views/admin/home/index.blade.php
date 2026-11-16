<x-admin.layout :title="__('admin.home.title')">
    <div class="adm-head">
        <h1>{{ __('admin.home.title') }}</h1>
        <div class="adm-actions">
            <a class="adm-btn" href="{{ route('admin.home.settings.edit') }}">{{ __('admin.home.settings') }}</a>
            @foreach (config('photoparty.locales') as $locale)
                <a class="adm-btn" href="{{ route('admin.home.preview', ['locale' => $locale]) }}" target="_blank" rel="noopener">{{ __('admin.home.preview_page') }} ({{ \App\Support\HomeSchema::localeLabel($locale) }})</a>
            @endforeach
        </div>
    </div>
    <p class="adm-muted">{{ __('admin.home.intro') }}</p>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.home.section') }}</th>
                    <th>{{ __('admin.home.status') }}</th>
                    <th>{{ __('admin.home.updated') }}</th>
                    <th>{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sections as $section)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><a href="{{ route('admin.home.sections.edit', $section) }}">{{ __('admin.home.sections.'.$section->key) }}</a></td>
                        <td><span @class(['adm-badge', 'adm-badge--active' => $section->enabled, 'adm-badge--cancelled' => ! $section->enabled])>{{ $section->enabled ? __('admin.common.on') : __('admin.common.off') }}</span></td>
                        <td>{{ $section->updated_at?->format('d/m/Y H:i') ?? __('admin.home.never') }}@if ($section->updater) &middot; {{ $section->updater->name }}@endif</td>
                        <td class="adm-actions">
                            <form method="POST" action="{{ route('admin.home.sections.toggle', $section) }}">
                                @csrf
                                <button type="submit" class="adm-btn adm-btn--sm">{{ $section->enabled ? __('admin.common.disable') : __('admin.common.enable') }}</button>
                            </form>
                            @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                <form method="POST" action="{{ route('admin.home.sections.move', $section) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" class="adm-btn adm-btn--sm" title="{{ __('admin.common.'.$direction) }}" aria-label="{{ __('admin.common.'.$direction) }}">{{ $arrow }}</button>
                                </form>
                            @endforeach
                            <a class="adm-btn adm-btn--sm" href="{{ route('admin.home.sections.edit', $section) }}">{{ __('admin.common.edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.layout>
