@props(['size' => 'h-8 w-8'])
{{-- Marca: cabine de fotos (lente) com o ponto amarelo do flash. --}}
<svg {{ $attributes->merge(['class' => $size.' shrink-0']) }} viewBox="0 0 32 32" aria-hidden="true" focusable="false">
    <rect width="32" height="32" rx="9" fill="#5b45e8"/>
    <circle cx="16" cy="17" r="8" fill="#fff"/>
    <circle cx="16" cy="17" r="4.6" fill="#181330"/>
    <circle cx="14.4" cy="15.4" r="1.4" fill="#fff"/>
    <circle cx="25" cy="7.5" r="3" fill="#ffd23f"/>
</svg>
