<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Identificação anônima do aparelho do convidado.
 *
 * Cookie "pp_device" com valor aleatório (criptografado pelo EncryptCookies do grupo web),
 * httponly, secure, samesite=lax, validade de config('photoparty.privacy.device_cookie_days')
 * (30 dias: cobre o evento e o período em que o convidado pode ver/baixar/excluir as próprias
 * fotos; minimização LGPD/GDPR). No banco só fica o HMAC-SHA256 do valor (photos.device_hash):
 * não identifica a pessoa e não permite reconstruir o cookie.
 */
class GuestDevice
{
    public const COOKIE = 'pp_device';

    /**
     * Garante o cookie do aparelho e devolve o hash. Se o aparelho ainda não tem
     * cookie (ou tem um inválido), cria um novo e o enfileira na resposta.
     */
    public function hash(Request $request): string
    {
        return static::hashId($this->resolveId($request));
    }

    public function resolveId(Request $request): string
    {
        $id = static::validId($request->cookie(self::COOKIE));

        if ($id === null) {
            $id = Str::random(40);
            Cookie::queue(Cookie::make(
                self::COOKIE,
                $id,
                self::lifetimeMinutes(),
                '/',
                null,
                $this->secure($request),
                true,
                false,
                'lax',
            ));
            // Disponível no restante desta requisição (rate limiter, etc.).
            $request->cookies->set(self::COOKIE, $id);
        }

        return $id;
    }

    public static function lifetimeMinutes(): int
    {
        return 60 * 24 * max(1, (int) config('photoparty.privacy.device_cookie_days', 30));
    }

    /**
     * Chave do rate limit por aparelho (o hash, nunca o valor do cookie).
     */
    public static function limiterKey(Request $request): ?string
    {
        $id = static::validId($request->cookie(self::COOKIE));

        return $id === null ? null : static::hashId($id);
    }

    public static function hashId(string $id): string
    {
        return hash_hmac('sha256', $id, (string) config('app.key'));
    }

    private static function validId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9]{40}$/', $value) ? $value : null;
    }

    /**
     * Sempre secure, exceto em HTTP no ambiente local/testes (o navegador descartaria o cookie).
     */
    private function secure(Request $request): bool
    {
        return $request->isSecure() || ! app()->environment(['local', 'testing']);
    }
}
