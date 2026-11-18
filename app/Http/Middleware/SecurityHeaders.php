<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers de segurança (SPEC 14, A02). Sem CDN: tudo vem de 'self'.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Token na URL: a página do convidado (/e/{slug}?t=...) e suas sub-rotas nunca mandam
        // Referer (nem para o próprio site: o token iria parar em logs de acesso). O telão
        // (/screen/{token}) define 'no-referrer' no próprio controller.
        if ($request->is('e/*') || $request->query->has('t')) {
            $headers->set('Referrer-Policy', 'no-referrer');
        } elseif (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        // O PHP (expose_php=On, comum em hospedagem compartilhada) manda "X-Powered-By: PHP/x.y"
        // fora do objeto Response; tira também do buffer de headers do SAPI.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    protected function contentSecurityPolicy(): string
    {
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", $dev],
            'style-src' => ["'self'", $dev],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'", $dev, $dev ? str_replace('http', 'ws', $dev) : null],
            'media-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    /**
     * Em desenvolvimento local, libera o servidor do Vite (`npm run dev`) na CSP.
     */
    protected function viteDevServer(): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        return preg_match('#^https?://[^\s;\'"]+$#', $url) ? rtrim($url, '/') : null;
    }
}
