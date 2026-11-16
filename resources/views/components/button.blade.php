@props(['variant' => 'primary', 'type' => 'submit'])
@php
    $classes = match ($variant) {
        'secondary' => 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        default => 'bg-indigo-600 text-white hover:bg-indigo-700',
    };
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold shadow-sm $classes"]) }}>{{ $slot }}</button>
