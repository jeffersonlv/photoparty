<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Texto de vencimento da lista de contas do admin.
 */
class ExpiryLabel
{
    /** Quantos dias (ou menos) faltando destacam o prazo. */
    public const URGENT_DAYS = 3;

    public static function format(CarbonInterface $at): string
    {
        return $at->format(app()->getLocale() === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i');
    }

    /**
     * @return array{text: string, urgent: bool}
     */
    public static function remaining(CarbonInterface $at, ?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();
        $seconds = $at->getTimestamp() - $now->getTimestamp();

        if ($seconds >= 0) {
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds, 3600);

            $text = match (true) {
                $days >= 1 => trans_choice('admin.accounts.remaining_days', $days, ['count' => $days]),
                $hours >= 1 => trans_choice('admin.accounts.remaining_hours', $hours, ['count' => $hours]),
                default => __('admin.accounts.due_today'),
            };

            return ['text' => $text, 'urgent' => $seconds <= self::URGENT_DAYS * 86400];
        }

        // Já passou do horário: se é ainda hoje, "vence hoje" (o comando diário ainda não rodou).
        if ($at->isSameDay($now)) {
            return ['text' => __('admin.accounts.due_today'), 'urgent' => true];
        }

        $days = max(1, intdiv(-$seconds, 86400));

        return ['text' => trans_choice('admin.accounts.overdue_days', $days, ['count' => $days]), 'urgent' => true];
    }
}
