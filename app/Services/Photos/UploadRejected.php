<?php

namespace App\Services\Photos;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Recusa de upload por regra de negócio ou falha de gravação, com status HTTP:
 * 410 evento fora do ar, 429 limite atingido, 507 sem espaço/erro de escrita.
 */
class UploadRejected extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $key, public readonly ?string $errorCode = null)
    {
        parent::__construct($key);
    }

    public static function ended(): self
    {
        return new self(410, 'upload.errors.event_not_live');
    }

    /**
     * Geofence em vigor e sem passe de local válido (ou vencido): o front volta ao passo de localização.
     */
    public static function locationRequired(): self
    {
        return new self(403, 'upload.errors.location_required', 'location_required');
    }

    public static function eventLimit(): self
    {
        return new self(429, 'upload.errors.event_limit');
    }

    public static function guestLimit(): self
    {
        return new self(429, 'upload.errors.guest_limit');
    }

    public static function storage(): self
    {
        return new self(507, 'upload.errors.storage');
    }

    /**
     * Recusa esperada: não vai para o log de erros (o 507 é logado no ponto da falha).
     */
    public function report(): void
    {
    }

    public function render(): JsonResponse
    {
        $body = ['message' => __($this->key)];

        if ($this->errorCode !== null) {
            $body['code'] = $this->errorCode;
        }

        return response()->json($body, $this->status);
    }
}
