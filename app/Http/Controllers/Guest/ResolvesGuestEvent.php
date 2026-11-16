<?php

namespace App\Http\Controllers\Guest;

use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Localiza o evento pelo slug + hash do token do QR (?t=). Qualquer divergência => 404:
 * o token de um evento não abre outro, e sem token o slug sozinho não revela nada.
 * Ignora o global scope da conta: o tenant vem do evento, não do usuário logado.
 */
trait ResolvesGuestEvent
{
    protected function resolveGuestEvent(Request $request, string $slug): Event
    {
        $token = $request->query('t');

        abort_unless(is_string($token) && strlen($token) === 64 && ctype_alnum($token), 404);

        return Event::withoutGlobalScopes()
            ->where('slug', $slug)
            ->whereUploadToken($token)
            ->firstOr(fn () => abort(404));
    }

    protected function guestToken(Request $request): string
    {
        return (string) $request->query('t');
    }
}
