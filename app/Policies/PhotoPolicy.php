<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Autorização de fotos. Foto de outra conta => 404 (denyAsNotFound), nunca 403.
 * Owner e moderator da mesma conta podem ver; admin pode ver (o acesso é auditado
 * pelo MediaController).
 */
class PhotoPolicy
{
    public function view(User $user, Photo $photo): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return $user->belongsToAccountId($photo->account_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
