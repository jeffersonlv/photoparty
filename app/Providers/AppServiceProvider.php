<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ProductionHardening;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Produção: URLs sempre de APP_URL (sem Host do request), cookie secure, debug desligado.
        ProductionHardening::apply($this->app->isProduction());

        // A09: bloqueio por excesso de tentativas de login vai para o log de segurança
        // (e-mail só como hash; nunca a senha).
        Event::listen(Lockout::class, function (Lockout $event) {
            Log::channel('security')->warning('Login bloqueado por excesso de tentativas', [
                'ip' => $event->request->ip(),
                'email_hash' => hash('sha256', mb_strtolower(trim((string) $event->request->input('email')))),
            ]);
        });

        // Alias registrado aqui (e não em bootstrap/app.php) para o módulo admin ficar isolado.
        Route::aliasMiddleware('admin', EnsureUserIsAdmin::class);

        // Senha forte (OWASP A07). Sem uncompromised(): consultaria API externa.
        Password::defaults(function () {
            return Password::min(10)->letters()->mixedCase()->numbers()->symbols();
        });

        // Cadastro: poucas tentativas por IP (anti-robô, SPEC 7).
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour((int) config('photoparty.rate_limits.register_per_hour'))->by('register|'.$request->ip());
        });

        // Login: limite grosso por IP; o limite fino por e-mail+IP fica no LoginRequest.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(20)->by('login|'.$request->ip());
        });

        // Telão (sem login): polling de 5 s = 12/min por tela; folga para várias telas no mesmo IP.
        RateLimiter::for('screen', function (Request $request) {
            return Limit::perMinute(120)->by('screen|'.$request->ip());
        });

        // Moderação ao vivo: polling de 5 s + ações do organizador.
        RateLimiter::for('moderation', function (Request $request) {
            return Limit::perMinute(180)->by('moderation|'.($request->user()?->getKey() ?? $request->ip()));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by('password-reset|'.$request->ip());
        });
    }
}
