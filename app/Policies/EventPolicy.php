<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Autorização de eventos. Recurso de outra conta => 404 (nunca 403),
 * para não revelar que o recurso existe (CLAUDE.md, regra 1).
 *
 * Papéis: owner gerencia tudo; moderator só visualiza (moderação vem no módulo 6).
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->account_id !== null;
    }

    public function view(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event) ?? Response::allow();
    }

    public function create(User $user): bool
    {
        return $user->account_id !== null && $user->isOwner();
    }

    public function update(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event)
            ?? $this->ownerOnly($user)
            ?? (in_array($event->computeStatus(), [EventStatus::Ended, EventStatus::Expired], true)
                ? Response::deny(__('app.errors.event_finished'))
                : Response::allow());
    }

    public function delete(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event)
            ?? $this->ownerOnly($user)
            ?? ($event->isDraft() ? Response::allow() : Response::deny(__('app.errors.only_draft_can_be_deleted')));
    }

    public function publish(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event) ?? $this->ownerOnly($user) ?? Response::allow();
    }

    public function close(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event)
            ?? $this->ownerOnly($user)
            ?? (in_array($event->computeStatus(), [EventStatus::Scheduled, EventStatus::Live], true)
                ? Response::allow()
                : Response::deny(__('app.errors.cannot_close')));
    }

    public function manageTokens(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event) ?? $this->ownerOnly($user) ?? Response::allow();
    }

    public function downloadQr(User $user, Event $event): Response
    {
        return $this->sameAccount($user, $event) ?? Response::allow();
    }

    /**
     * null quando o evento é da conta do usuário; 404 caso contrário.
     */
    protected function sameAccount(User $user, Event $event): ?Response
    {
        return $user->belongsToAccountId($event->account_id) ? null : Response::denyAsNotFound();
    }

    protected function ownerOnly(User $user): ?Response
    {
        return $user->isOwner() ? null : Response::deny(__('app.errors.owner_only'));
    }
}
