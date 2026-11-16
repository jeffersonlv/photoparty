<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idioma da interface: escolhido pelo usuário (sessão) ou o padrão da aplicação.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;

        // Sempre define o idioma (padrão da aplicação sem escolha na sessão): evita vazar o
        // idioma de uma requisição para a seguinte quando a mesma instância é reaproveitada.
        App::setLocale(is_string($locale) && in_array($locale, config('photoparty.locales'), true)
            ? $locale
            : config('app.locale'));

        return $next($request);
    }
}
