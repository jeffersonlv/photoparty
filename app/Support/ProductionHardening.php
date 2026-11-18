<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Travas de produção aplicadas no boot (OWASP A02/A07), independentes do .env estar certo.
 *
 * - URLs geradas (link de redefinição de senha, verificação de e-mail, redirecionamentos)
 *   usam sempre APP_URL, nunca o header Host do request (envenenamento de link).
 * - APP_URL em https => cookie de sessão sempre "secure" e esquema https forçado.
 * - APP_DEBUG=true em produção é desligado na hora e registrado como crítico
 *   (stack trace nunca vai para o navegador).
 */
class ProductionHardening
{
    public static function apply(bool $production): void
    {
        if (! $production) {
            return;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && preg_match('#^https?://[^/\s]+#i', $appUrl)) {
            URL::forceRootUrl($appUrl);

            if (str_starts_with(strtolower($appUrl), 'https://')) {
                URL::forceScheme('https');
                config(['session.secure' => true]);
            }
        }

        if (config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG=true em produção: desligado automaticamente. Corrija o .env.');
        }
    }
}
