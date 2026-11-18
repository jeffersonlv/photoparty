<?php

namespace App\Services\Geofence;

use App\Models\Event;
use Illuminate\Contracts\Session\Session;

/**
 * Conferência "envio só no local do evento".
 *
 * Privacidade (LGPD/GDPR): a posição do convidado entra só como argumento deste cálculo e é
 * descartada no fim da requisição. Nada de coordenadas em banco, log ou sessão: o passe guarda
 * apenas o hash do aparelho e a validade.
 *
 * Coordenadas vêm do aparelho e podem ser falsificadas por quem souber; precisão máxima,
 * leitura recente, passe curto e conferência no servidor reduzem o abuso casual.
 */
final class LocationCheck
{
    public const OK = 'ok';

    public const OUTSIDE = 'outside';

    public const INACCURATE = 'inaccurate';

    public const STALE = 'stale';

    private const EARTH_RADIUS_M = 6371008.8;

    private const SESSION_KEY = 'geofence_pass';

    /** Distância em metros entre dois pontos (fórmula de haversine). */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Decide a leitura do convidado.
     *
     * @param  int  $timestampMs  momento da leitura (Position.timestamp, em ms)
     * @return array{result: string, distance_m: ?int}
     */
    public function evaluate(Event $event, float $lat, float $lng, float $accuracy, int $timestampMs, ?int $nowMs = null): array
    {
        $maxAccuracy = (float) config('photoparty.geofence.max_accuracy_m', 150);
        $maxAgeMs = 1000 * (int) config('photoparty.geofence.max_age_seconds', 120);
        $nowMs ??= (int) now()->getTimestampMs();

        // Leitura velha (ou com relógio muito adiantado): pede outra.
        if (abs($nowMs - $timestampMs) > $maxAgeMs) {
            return ['result' => self::STALE, 'distance_m' => null];
        }

        if ($accuracy > $maxAccuracy) {
            return ['result' => self::INACCURATE, 'distance_m' => null];
        }

        $distance = self::distanceMeters((float) $event->geofence_lat, (float) $event->geofence_lng, $lat, $lng);
        $radius = (int) $event->geofence_radius_m;

        if ($distance - min($accuracy, $maxAccuracy) <= $radius) {
            return ['result' => self::OK, 'distance_m' => null];
        }

        return ['result' => self::OUTSIDE, 'distance_m' => self::roundDistance($distance)];
    }

    /** Distância aproximada para o convidado: dezenas de metros até 1 km; depois centenas. */
    public static function roundDistance(float $meters): int
    {
        return $meters < 1000
            ? (int) (round($meters / 10) * 10)
            : (int) (round($meters / 100) * 100);
    }

    /** "340 m" / "1,2 km" (mesmo formato do front, resources/js/guest/location.js). */
    public static function formatDistance(int $meters, string $locale = 'pt_BR'): string
    {
        if ($meters < 1000) {
            return $meters.' m';
        }

        $km = number_format($meters / 1000, 1, str_starts_with($locale, 'en') ? '.' : ',', '');

        return $km.' km';
    }

    /** Grava o passe de local deste evento para este aparelho. Devolve a validade em segundos. */
    public function grantPass(Session $session, Event $event, string $deviceHash, ?int $now = null): int
    {
        $ttl = 60 * max(1, (int) config('photoparty.geofence.pass_minutes', 30));
        $passes = $this->passes($session);

        // Só o passe deste evento muda; passes vencidos de outros eventos saem.
        $now ??= now()->getTimestamp();
        $passes = array_filter($passes, fn (array $p) => ($p['until'] ?? 0) > $now);
        $passes[$event->uuid] = ['device' => $deviceHash, 'until' => $now + $ttl];
        $session->put(self::SESSION_KEY, $passes);

        return $ttl;
    }

    /** Segundos restantes do passe válido (0 se não houver). */
    public function passRemaining(Session $session, Event $event, ?string $deviceHash, ?int $now = null): int
    {
        $now ??= now()->getTimestamp();

        if (! $this->hasValidPass($session, $event, $deviceHash, $now)) {
            return 0;
        }

        return (int) $this->passes($session)[$event->uuid]['until'] - $now;
    }

    /** Passe válido: do mesmo evento, do mesmo aparelho e dentro da validade. */
    public function hasValidPass(Session $session, Event $event, ?string $deviceHash, ?int $now = null): bool
    {
        if ($deviceHash === null) {
            return false;
        }

        $pass = $this->passes($session)[$event->uuid] ?? null;

        return is_array($pass)
            && is_string($pass['device'] ?? null)
            && hash_equals($pass['device'], $deviceHash)
            && (int) ($pass['until'] ?? 0) > ($now ?? now()->getTimestamp());
    }

    /**
     * @return array<string, array{device: string, until: int}>
     */
    private function passes(Session $session): array
    {
        $passes = $session->get(self::SESSION_KEY, []);

        return is_array($passes) ? $passes : [];
    }
}
