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
    public function __construct(public readonly int $status, public readonly string $key)
    {
        parent::__construct($key);
    }

    public static function ended(): self
    {
        return new self(410, 'upload.errors.event_not_live');
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
        return response()->json(['message' => __($this->key)], $this->status);
    }
}
