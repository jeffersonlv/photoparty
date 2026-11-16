@php($editing = $plan->exists)
<x-admin.layout :title="$editing ? __('admin.plans.edit') : __('admin.plans.new')">
    <h1>{{ $editing ? __('admin.plans.edit') : __('admin.plans.new') }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="adm-card adm-form">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="adm-field">
            <label for="name">{{ __('admin.plans.name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $plan->name) }}" maxlength="100" required>
        </div>
        <div class="adm-field">
            <label for="slug">{{ __('admin.plans.slug') }}</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $plan->slug) }}" maxlength="100" required>
        </div>
        <div class="adm-row">
            <div class="adm-field">
                <label for="price">{{ __('admin.plans.price') }}</label>
                <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $plan->price) }}" required>
            </div>
            <div class="adm-field">
                <label for="currency">{{ __('admin.plans.currency') }}</label>
                <input id="currency" name="currency" type="text" value="{{ old('currency', $plan->currency) }}" maxlength="3" required>
            </div>
            <div class="adm-field">
                <label for="sort_order">{{ __('admin.plans.sort_order') }}</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
            </div>
        </div>
        <div class="adm-row">
            <div class="adm-field">
                <label for="max_events_per_month">{{ __('admin.plans.max_events') }}</label>
                <input id="max_events_per_month" name="max_events_per_month" type="number" min="1" value="{{ old('max_events_per_month', $plan->max_events_per_month) }}">
                <small class="adm-muted">{{ __('admin.plans.max_events_hint') }}</small>
            </div>
            <div class="adm-field">
                <label for="max_photos_per_event">{{ __('admin.plans.max_photos') }}</label>
                <input id="max_photos_per_event" name="max_photos_per_event" type="number" min="1" value="{{ old('max_photos_per_event', $plan->max_photos_per_event) }}" required>
            </div>
            <div class="adm-field">
                <label for="retention_days">{{ __('admin.plans.retention') }}</label>
                <input id="retention_days" name="retention_days" type="number" min="1" value="{{ old('retention_days', $plan->retention_days) }}" required>
            </div>
        </div>
        <label class="adm-check"><input type="checkbox" name="allows_custom_frames" value="1" @checked(old('allows_custom_frames', $plan->allows_custom_frames))> {{ __('admin.plans.custom_frames') }}</label>
        <label class="adm-check"><input type="checkbox" name="white_label" value="1" @checked(old('white_label', $plan->white_label))> {{ __('admin.plans.white_label') }}</label>
        <label class="adm-check"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public))> {{ __('admin.plans.is_public') }}</label>

        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
            <a class="adm-btn adm-btn--ghost" href="{{ route('admin.plans.index') }}">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-admin.layout>
