<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('photoparty.locales'), true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->to($this->safePrevious($request));
    }

    /**
     * Volta para a página anterior SÓ se ela for deste site (OWASP A01: open redirect).
     * back() usa o header Referer, que um site externo controla ao linkar para /locale/{x}.
     */
    private function safePrevious(Request $request): string
    {
        $fallback = route('home', absolute: false);
        $previous = url()->previous($fallback);

        $target = parse_url($previous);
        $here = parse_url($request->getSchemeAndHttpHost());

        if (! is_array($target) || ! isset($target['host'])) {
            return $fallback;
        }

        $sameOrigin = strcasecmp($target['host'], (string) ($here['host'] ?? '')) === 0
            && ($target['port'] ?? null) === ($here['port'] ?? null)
            && ($target['scheme'] ?? '') === ($here['scheme'] ?? '');

        if (! $sameOrigin) {
            return $fallback;
        }

        $path = '/'.ltrim($target['path'] ?? '', '/');

        // Nunca de volta para a própria troca de idioma (laço) nem para "//host" (protocol-relative).
        if (str_starts_with($path, '//') || str_starts_with($path, '/locale/')) {
            return $fallback;
        }

        return $path.(isset($target['query']) ? '?'.$target['query'] : '');
    }
}
