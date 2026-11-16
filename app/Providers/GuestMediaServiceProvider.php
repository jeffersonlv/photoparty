<?php

namespace App\Providers;

use App\Services\GuestDevice;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate limiters dos módulos 3-4 (convidado e mídia). Provider próprio para não
 * conflitar com o AppServiceProvider editado por outros módulos.
 */
class GuestMediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Upload do convidado (A06): por aparelho e, como teto, por IP (rede do salão é compartilhada).
        RateLimiter::for('guest-upload', function (Request $request) {
            $limits = [Limit::perMinute(60)->by('guest-upload-ip|'.$request->ip())];

            if ($device = GuestDevice::limiterKey($request)) {
                $limits[] = Limit::perMinute(6)->by('guest-upload-device|'.$device);
            }

            return $limits;
        });

        // Página do convidado (GET /e/...): protege contra varredura de slugs/tokens.
        RateLimiter::for('guest-page', function (Request $request) {
            return Limit::perMinute(60)->by('guest-page|'.$request->ip());
        });

        // Entrega de imagens: telão e painel carregam muitas miniaturas.
        RateLimiter::for('media', function (Request $request) {
            return Limit::perMinute(600)->by('media|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
