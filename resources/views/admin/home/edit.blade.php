<x-admin.layout :title="__('admin.home.sections.'.$section->key)">
    <p><a href="{{ route('admin.home.index') }}">&larr; {{ __('admin.home.title') }}</a></p>
    <h1>{{ __('admin.home.sections.'.$section->key) }}</h1>

    <form method="POST" action="{{ route('admin.home.sections.update', $section) }}" class="adm-card adm-form" id="section-form">
        @csrf
        @method('PUT')

        <x-admin.fields :fields="$schema['fields']" prefix="content" :values="$section->content ?? []" />

        @if ($section->key === 'pricing')
            <p class="adm-muted">{{ __('admin.home.pricing_note') }}</p>
        @endif

        {{-- Prévia sem salvar: o mesmo formulário é enviado (formaction) para a rota de prévia, em nova aba. --}}
        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
            <select name="preview_locale" aria-label="{{ __('admin.home.preview_locale') }}">
                @foreach (config('photoparty.locales') as $locale)
                    <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>{{ \App\Support\HomeSchema::localeLabel($locale) }}</option>
                @endforeach
            </select>
            <button type="submit" class="adm-btn" formaction="{{ route('admin.home.sections.preview', $section) }}" formtarget="_blank">{{ __('admin.home.preview_section') }}</button>
        </div>
    </form>

    <section class="adm-card">
        <div class="adm-head">
            <h2>{{ __('admin.home.items_title') }}</h2>
            @if ($schema['items'])
                <a class="adm-btn adm-btn--primary" href="{{ route('admin.home.items.create', $section) }}">{{ __('admin.home.add_item') }}</a>
            @endif
        </div>

        @if (! $schema['items'])
            <p class="adm-muted">{{ __('admin.home.no_items') }}</p>
        @else
            <div class="adm-table-wrap">
                <table class="adm-table adm-table--stack">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('admin.home.fields.title') }}</th>
                            <th>{{ __('admin.home.status') }}</th>
                            <th>{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section->items as $item)
                            @php($firstField = array_key_first($schema['items']['fields']))
                            <tr>
                                <td data-label="#">{{ $loop->iteration }}</td>
                                <td data-label="{{ __('admin.home.fields.title') }}">{{ \Illuminate\Support\Str::limit($item->data[app()->getLocale()][$firstField] ?? ($item->data['pt_BR'][$firstField] ?? ''), 80) }}</td>
                                <td data-label="{{ __('admin.home.status') }}"><span @class(['adm-badge', 'adm-badge--active' => $item->enabled, 'adm-badge--cancelled' => ! $item->enabled])>{{ $item->enabled ? __('admin.common.on') : __('admin.common.off') }}</span></td>
                                <td class="adm-actions">
                                    <form method="POST" action="{{ route('admin.home.items.toggle', [$section, $item]) }}">
                                        @csrf
                                        <button type="submit" class="adm-btn adm-btn--sm">{{ $item->enabled ? __('admin.common.disable') : __('admin.common.enable') }}</button>
                                    </form>
                                    @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                        <form method="POST" action="{{ route('admin.home.items.move', [$section, $item]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="{{ $direction }}">
                                            <button type="submit" class="adm-btn adm-btn--sm" title="{{ __('admin.common.'.$direction) }}" aria-label="{{ __('admin.common.'.$direction) }}">{{ $arrow }}</button>
                                        </form>
                                    @endforeach
                                    <a class="adm-btn adm-btn--sm" href="{{ route('admin.home.items.edit', [$section, $item]) }}">{{ __('admin.common.edit') }}</a>
                                    <x-admin.delete :action="route('admin.home.items.destroy', [$section, $item])" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="adm-empty">{{ __('admin.home.no_items') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-admin.layout>
