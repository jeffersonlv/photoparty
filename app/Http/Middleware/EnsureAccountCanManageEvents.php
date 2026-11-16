<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia criar/publicar eventos fora de `trial` (com teste disponível) e `active` (SPEC 7).
 */
class EnsureAccountCanManageEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->account;

        if ($account === null) {
            abort(404);
        }

        if (! $account->canManageEvents()) {
            $message = __('app.errors.account_cannot_manage_events');

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()->route('events.index')->with('error', $message);
        }

        return $next($request);
    }
}
