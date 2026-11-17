{{--
    Prévia de uma orientação: fundo (se houver) → "foto de exemplo" (gradiente + formas, na
    window) → overlay. SVG com atributos de apresentação: compatível com a CSP (sem style inline).
    Recebe: $id (único na página), $o (config da orientação), $bg/$overlay (URLs ou null), $label.
--}}
@php($w = (int) $o['window']['w'])
@php($h = (int) $o['window']['h'])
@php($x = (int) $o['window']['x'])
@php($y = (int) $o['window']['y'])
<svg viewBox="0 0 {{ (int) $o['width'] }} {{ (int) $o['height'] }}" class="h-auto w-full rounded-lg bg-slate-100 ring-1 ring-line" role="img" aria-label="{{ $label }}">
    <defs>
        <linearGradient id="pp-sample-{{ $id }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#38bdf8" />
            <stop offset="0.55" stop-color="#e879f9" />
            <stop offset="1" stop-color="#fbbf24" />
        </linearGradient>
    </defs>
    @if ($bg)
        <image href="{{ $bg }}" x="0" y="0" width="{{ (int) $o['width'] }}" height="{{ (int) $o['height'] }}" preserveAspectRatio="none" />
    @endif
    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $h }}" fill="url(#pp-sample-{{ $id }})" />
    <circle cx="{{ $x + intdiv($w, 2) }}" cy="{{ $y + intdiv($h * 2, 5) }}" r="{{ intdiv(min($w, $h), 6) }}" fill="#fff" fill-opacity="0.75" />
    <ellipse cx="{{ $x + intdiv($w, 2) }}" cy="{{ $y + $h }}" rx="{{ intdiv(min($w, $h), 3) }}" ry="{{ intdiv(min($w, $h), 4) }}" fill="#fff" fill-opacity="0.75" />
    @if ($overlay)
        <image href="{{ $overlay }}" x="0" y="0" width="{{ (int) $o['width'] }}" height="{{ (int) $o['height'] }}" preserveAspectRatio="none" />
    @endif
</svg>
