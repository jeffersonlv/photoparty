<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Regras de agenda, teste grátis e transições de estado dos eventos.
 * Tudo decidido no servidor (CLAUDE.md, regra 3); o front só exibe.
 */
class EventScheduler
{
    /**
     * Pode a conta criar mais um evento? Retorna mensagem de erro ou null.
     */
    public function creationError(Account $account): ?string
    {
        if (! $account->canManageEvents()) {
            return __('app.errors.account_cannot_manage_events');
        }

        if ($account->isOnTrial() && Event::withoutGlobalScopes()->where('account_id', $account->id)->exists()) {
            return __('app.errors.trial_single_event');
        }

        if ($account->isActive() && ($limit = $account->plan?->max_events_per_month)) {
            $count = Event::withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            if ($count >= $limit) {
                return __('app.errors.plan_monthly_limit', ['limit' => $limit]);
            }
        }

        return null;
    }

    /**
     * Valida início/fim de um evento (novo ou existente). Retorna [campo => mensagem].
     *
     * @return array<string, string>
     */
    public function windowErrors(Account $account, bool $isTrial, CarbonInterface $startsAt, CarbonInterface $endsAt): array
    {
        $errors = [];

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return ['ends_at' => __('app.errors.ends_before_start')];
        }

        if ($isTrial) {
            $maxHours = (int) config('photoparty.trial.max_event_hours');

            if ($endsAt->greaterThan($startsAt->copy()->addHours($maxHours))) {
                $errors['ends_at'] = __('app.errors.trial_max_duration', ['hours' => $maxHours]);
            }

            // Depois de publicado o teste, o evento não pode passar do fim do período da conta.
            if ($account->trial_ends_at !== null && $endsAt->greaterThan($account->trial_ends_at)) {
                $errors['ends_at'] = __('app.errors.trial_window_exceeded', [
                    'time' => $account->trial_ends_at->format('d/m/Y H:i'),
                ]);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function publishErrors(Account $account, Event $event): array
    {
        if (! $event->isDraft()) {
            return ['event' => __('app.errors.already_published')];
        }

        if (! $account->canManageEvents()) {
            return ['event' => __('app.errors.account_cannot_manage_events')];
        }

        $now = now();

        if ($event->ends_at->lessThanOrEqualTo($now)) {
            return ['ends_at' => __('app.errors.ends_in_past')];
        }

        $errors = $this->windowErrors($account, $event->is_trial, $event->starts_at, $event->ends_at);

        if ($event->is_trial) {
            $windowHours = (int) config('photoparty.trial.account_window_hours');

            // O período de teste da conta (3 h) começa agora; o evento precisa caber nele.
            if ($event->ends_at->greaterThan($now->copy()->addHours($windowHours))) {
                $errors['ends_at'] = __('app.errors.trial_publish_window', ['hours' => $windowHours]);
            }
        }

        return $errors;
    }

    public function publish(Account $account, Event $event): void
    {
        DB::transaction(function () use ($account, $event) {
            if ($event->is_trial) {
                $account->startTrialWindow();
            }

            $event->published_at = now();
            $event->expires_at = $event->calculateExpiresAt($account->retentionDays());
            $event->refreshStatus();
            $event->save();

            AuditLog::record('event.published', $event, ['trial' => $event->is_trial]);
        });
    }

    public function close(Account $account, Event $event): void
    {
        $event->closed_at = now();
        $event->expires_at = $event->calculateExpiresAt($account->retentionDays());
        $event->refreshStatus();
        $event->save();

        AuditLog::record('event.closed', $event);
    }

    /**
     * Depois de editar horários de um evento publicado, recalcula expiração e estado.
     */
    public function syncAfterUpdate(Account $account, Event $event): void
    {
        if (! $event->isDraft()) {
            $event->expires_at = $event->calculateExpiresAt($account->retentionDays());
        }

        $event->refreshStatus();
    }
}
