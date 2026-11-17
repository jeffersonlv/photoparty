@props(['variant' => 'primary', 'type' => 'submit'])
@php
    $classes = match ($variant) {
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
        'danger-outline' => 'btn-danger-outline',
        'flash' => 'btn-flash',
        default => 'btn-primary',
    };
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => "btn $classes"]) }}>{{ $slot }}</button>
