<?php

use App\Http\Middleware\EnsureAccountCanManageEvents;
use App\Http\Middleware\EnsureUserHasAccount;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Um arquivo de rotas por módulo, para equipes/agentes trabalharem em paralelo
            // sem editar o mesmo arquivo. Todos usam o grupo de middleware "web".
            foreach (['guest', 'screen', 'photos', 'admin', 'home'] as $module) {
                $file = base_path("routes/{$module}.php");

                if (is_file($file)) {
                    \Illuminate\Support\Facades\Route::middleware('web')->group($file);
                }
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Headers de segurança em toda resposta (inclusive erros).
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'account' => EnsureUserHasAccount::class,
            'account.can-manage-events' => EnsureAccountCanManageEvents::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Nunca registrar senhas/tokens no log de validação.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            't',
            'token',
        ]);
    })->create();
