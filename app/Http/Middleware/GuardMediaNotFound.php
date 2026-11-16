<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rajadas de 404/403 em /media (SPEC 13.8, A09): quem erra muitas vezes seguidas está
 * provavelmente adivinhando UUIDs ou adulterando assinaturas.
 *
 * - Cada 404/403 conta um ponto por IP (janela de 1 min).
 * - Ao atingir o limite, registra um aviso no canal "security" (uma vez por janela)
 *   e passa a responder 429 até a janela expirar.
 * O log nunca inclui a query string (assinatura) nem cookies.
 */
class GuardMediaNotFound
{
    public const MAX_MISSES_PER_MINUTE = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'media-miss|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_MISSES_PER_MINUTE)) {
            abort(429);
        }

        $response = $next($request);

        if (in_array($response->getStatusCode(), [403, 404], true)) {
            $misses = RateLimiter::hit($key, 60);

            if ($misses === self::MAX_MISSES_PER_MINUTE) {
                Log::channel('security')->warning('Rajada de 404/403 em /media', [
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'misses' => $misses,
                ]);
            }
        }

        return $response;
    }
}
