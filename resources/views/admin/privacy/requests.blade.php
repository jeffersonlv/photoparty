<x-admin.layout :title="__('admin.privacy.title')">
    <div class="adm-head">
        <h1>{{ __('admin.privacy.title') }} <span class="adm-badge adm-badge--trial">{{ __('admin.privacy.open_count', ['count' => $openCount]) }}</span></h1>
        <a class="adm-btn" href="{{ route('admin.privacy.settings.edit') }}">{{ __('admin.privacy.settings') }}</a>
    </div>

    @if ($missing !== [])
        <div class="adm-alert adm-alert--err" role="alert">
            {{ __('admin.privacy.missing_warning', ['fields' => implode(', ', array_map(fn ($k) => __('admin.privacy.fields.'.$k), $missing))]) }}
            <a href="{{ route('admin.privacy.settings.edit') }}">{{ __('admin.privacy.settings') }}</a>
        </div>
    @endif

    <p class="adm-muted">{{ __('admin.privacy.how_to') }}</p>

    <nav class="adm-inline" aria-label="{{ __('admin.privacy.status') }}">
        @foreach (['open' => 'filter_open', 'done' => 'filter_done', 'all' => 'filter_all'] as $value => $label)
            <a href="{{ route('admin.privacy.requests.index', ['status' => $value]) }}" @class(['adm-btn adm-btn--sm', 'adm-btn--primary' => $status === $value]) @if ($status === $value) aria-current="page" @endif>{{ __('admin.privacy.'.$label) }}</a>
        @endforeach
    </nav>

    <div class="adm-list">
        @forelse ($requests as $item)
            <article class="adm-card">
                <dl class="adm-dl">
                    <dt>{{ __('admin.privacy.received') }}</dt>
                    <dd>{{ $item->created_at->format('d/m/Y H:i') }} · <code>{{ substr($item->uuid, 0, 8) }}</code></dd>
                    <dt>{{ __('admin.privacy.type') }}</dt>
                    <dd>{{ __('privacy.types.'.$item->type) }}</dd>
                    <dt>{{ __('admin.privacy.requester') }}</dt>
                    <dd>{{ $item->email }}</dd>
                    <dt>{{ __('admin.privacy.event') }}</dt>
                    <dd>
                        @if ($item->event)
                            {{ $item->event->name }} ({{ $item->event->slug }})
                            @if ($item->account) · <a href="{{ route('admin.accounts.show', $item->account) }}">{{ $item->account->name }}</a>@endif
                        @else
                            {{ __('admin.privacy.event_unknown') }}
                        @endif
                    </dd>
                    <dt>{{ __('admin.privacy.link') }}</dt>
                    <dd><code class="adm-meta">{{ $item->event_link }}</code></dd>
                    @if ($item->details)
                        <dt>{{ __('admin.privacy.details') }}</dt>
                        <dd>{{ $item->details }}</dd>
                    @endif
                    <dt>{{ __('admin.privacy.deadlines') }}</dt>
                    <dd>
                        @foreach (['lgpd', 'gdpr'] as $law)
                            <span>{{ __('admin.privacy.due_'.$law, ['days' => config('photoparty.privacy.response_days.'.$law), 'date' => $item->dueAt($law)->format('d/m/Y')]) }}@if ($item->isOverdue($law)) <span class="adm-badge adm-badge--cancelled">{{ __('admin.privacy.overdue') }}</span>@endif</span><br>
                        @endforeach
                    </dd>
                    <dt>{{ __('admin.privacy.status') }}</dt>
                    <dd>
                        <span @class(['adm-badge', 'adm-badge--trial' => $item->isOpen(), 'adm-badge--active' => ! $item->isOpen()])>{{ __('admin.privacy.statuses.'.$item->status) }}</span>
                        @if ($item->completed_at) {{ __('admin.privacy.completed_at', ['date' => $item->completed_at->format('d/m/Y H:i')]) }}@endif
                    </dd>
                </dl>
                <form method="POST" action="{{ route('admin.privacy.requests.update', $item) }}" class="adm-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="{{ $item->isOpen() ? 'done' : 'open' }}">
                    <div class="adm-field">
                        <label for="note-{{ $item->uuid }}">{{ __('admin.privacy.note') }}</label>
                        <textarea id="note-{{ $item->uuid }}" name="resolution_note" rows="2" maxlength="2000">{{ $item->resolution_note }}</textarea>
                    </div>
                    <div class="adm-actions">
                        <button type="submit" @class(['adm-btn', 'adm-btn--primary' => $item->isOpen()])>{{ $item->isOpen() ? __('admin.privacy.mark_done') : __('admin.privacy.reopen') }}</button>
                    </div>
                </form>
            </article>
        @empty
            <p class="adm-empty">{{ __('admin.common.empty') }}</p>
        @endforelse
    </div>

    {{ $requests->links('admin.pagination') }}
</x-admin.layout>
