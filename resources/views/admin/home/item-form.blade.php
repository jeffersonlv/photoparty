@php($editing = $item->exists)
<x-admin.layout :title="$editing ? __('admin.home.edit_item') : __('admin.home.new_item')">
    <p><a href="{{ route('admin.home.sections.edit', $section) }}">&larr; {{ __('admin.home.sections.'.$section->key) }}</a></p>
    <h1>{{ $editing ? __('admin.home.edit_item') : __('admin.home.new_item') }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.home.items.update', [$section, $item]) : route('admin.home.items.store', $section) }}" class="adm-card adm-form">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.fields :fields="$schema['fields']" prefix="data" :values="$item->data ?? []" />

        <label class="adm-check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $editing ? $item->enabled : true))> {{ __('admin.home.item_enabled') }}</label>

        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn--primary">{{ __('admin.common.save') }}</button>
            <a class="adm-btn adm-btn--ghost" href="{{ route('admin.home.sections.edit', $section) }}">{{ __('admin.common.cancel') }}</a>
        </div>
    </form>
</x-admin.layout>
