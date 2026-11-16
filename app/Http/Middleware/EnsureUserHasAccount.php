<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Área do organizador: exige usuário ligado a uma conta (owner/moderator).
 * Admin (sem conta) não usa estas rotas: responde 404.
 */
class EnsureUserHasAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->account_id === null || $user->account === null) {
            abort(404);
        }

        return $next($request);
    }
}
