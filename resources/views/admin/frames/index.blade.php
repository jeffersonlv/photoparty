<x-admin.layout :title="__('admin.frames.title')">
    <h1>{{ __('admin.frames.title') }}</h1>
    <p class="adm-muted">{{ __('admin.frames.intro') }}</p>

    <div class="adm-table-wrap">
        <table class="adm-table adm-table--stack">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.frames.name') }}</th>
                    <th>{{ __('admin.frames.key') }}</th>
                    <th>{{ __('admin.frames.status') }}</th>
                    <th>{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($frames as $frame)
                    <tr>
                        <td data-label="#">{{ $loop->iteration }}</td>
                        <td data-label="{{ __('admin.frames.name') }}">{{ $frame->name }}</td>
                        <td data-label="{{ __('admin.frames.key') }}">{{ $frame->template_key ?? '—' }}</td>
                        <td data-label="{{ __('admin.frames.status') }}"><span @class(['adm-badge', 'adm-badge--active' => $frame->is_active, 'adm-badge--cancelled' => ! $frame->is_active])>{{ $frame->is_active ? __('admin.common.on') : __('admin.common.off') }}</span></td>
                        <td class="adm-actions" data-label="{{ __('admin.common.actions') }}">
                            <form method="POST" action="{{ route('admin.frames.toggle', $frame) }}">
                                @csrf
                                <button type="submit" class="adm-btn adm-btn--sm">{{ $frame->is_active ? __('admin.common.disable') : __('admin.common.enable') }}</button>
                            </form>
                            @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                <form method="POST" action="{{ route('admin.frames.move', $frame) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" class="adm-btn adm-btn--sm" title="{{ __('admin.common.'.$direction) }}" aria-label="{{ __('admin.common.'.$direction) }}">{{ $arrow }}</button>
                                </form>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="adm-empty">{{ __('admin.common.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
