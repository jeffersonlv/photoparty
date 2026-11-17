<?php

namespace App\Policies;

use App\Models\Frame;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Molduras personalizadas (módulo 8).
 *
 * - Moldura de outra conta (ou modelo do sistema nas ações de custom) => 404.
 * - Moderador só visualiza.
 * - Enviar/ativar exige Account::canUseCustomFrames() (plano pago): senão 403 com a
 *   mensagem amigável (é recurso da própria conta, então 403 não revela nada).
 * - Excluir continua permitido após rebaixar o plano (para limpar).
 */
class FramePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_id !== null;
    }

    public function view(User $user, Frame $frame): Response
    {
        return $this->ownCustom($user, $frame) ?? Response::allow();
    }

    public function create(User $user): Response
    {
        if ($user->account_id === null) {
            return Response::denyAsNotFound();
        }

        return $this->ownerOnly($user) ?? $this->paidPlan($user) ?? Response::allow();
    }

    public function update(User $user, Frame $frame): Response
    {
        return $this->ownCustom($user, $frame) ?? $this->ownerOnly($user) ?? $this->paidPlan($user) ?? Response::allow();
    }

    public function delete(User $user, Frame $frame): Response
    {
        return $this->ownCustom($user, $frame) ?? $this->ownerOnly($user) ?? Response::allow();
    }

    /**
     * null quando a moldura é custom da conta do usuário; 404 caso contrário.
     */
    protected function ownCustom(User $user, Frame $frame): ?Response
    {
        return $frame->isCustom() && $frame->account_id !== null && $user->belongsToAccountId($frame->account_id)
            ? null
            : Response::denyAsNotFound();
    }

    protected function ownerOnly(User $user): ?Response
    {
        return $user->isOwner() ? null : Response::deny(__('app.errors.owner_only'));
    }

    protected function paidPlan(User $user): ?Response
    {
        return $user->account?->canUseCustomFrames() ? null : Response::deny(__('frames.paid_only'));
    }
}
