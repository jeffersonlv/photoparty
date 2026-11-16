<?php

namespace App\Services;

use App\Models\Event;

/**
 * Configurações do telão normalizadas (events.screen_settings + defaults).
 * Valores inválidos voltam ao padrão: o telão nunca quebra por configuração.
 */
class ScreenSettings
{
    public const TRANSITIONS = ['fade', 'slide', 'zoom', 'none'];

    /**
     * @return array{seconds_per_photo: int, transition: string, show_qr: bool}
     */
    public static function for(Event $event): array
    {
        $defaults = config('photoparty.screen_defaults');
        $stored = is_array($event->screen_settings) ? $event->screen_settings : [];
        $merged = array_merge($defaults, $stored);

        $transition = $merged['transition'] ?? 'fade';

        return [
            'seconds_per_photo' => max(3, min(60, (int) ($merged['seconds_per_photo'] ?? 6))),
            'transition' => in_array($transition, self::TRANSITIONS, true) ? $transition : 'fade',
            'show_qr' => (bool) ($merged['show_qr'] ?? true),
        ];
    }
}
