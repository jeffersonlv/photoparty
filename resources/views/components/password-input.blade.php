@props(['name', 'id' => null, 'autocomplete' => 'current-password'])
@php $id ??= $name; @endphp
<div x-data="passwordToggle" class="relative mt-1"
     data-show="{{ __('app.auth.show_password') }}" data-hide="{{ __('app.auth.hide_password') }}">
    <input id="{{ $id }}" name="{{ $name }}" type="password" x-bind:type="inputType" required autocomplete="{{ $autocomplete }}"
           class="input mt-0 pr-20">
    <button type="button" x-on:click="toggle" x-text="label"
            class="absolute inset-y-0 right-0 px-3 text-xs text-slate-500 hover:text-slate-800">{{ __('app.auth.show_password') }}</button>
</div>
