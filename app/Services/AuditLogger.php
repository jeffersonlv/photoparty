<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra ações do admin em audit_logs (OWASP A09).
 *
 * Nunca grava senhas, tokens ou segredos: chaves sensíveis são removidas de meta.
 */
class AuditLogger
{
    private const SENSITIVE = ['password', 'token', 'secret', 'key', 'hash'];

    public function log(string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::record($action, $subject, $this->clean($meta));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function clean(array $meta): array
    {
        foreach ($meta as $name => $value) {
            if (is_string($name) && preg_match('/'.implode('|', self::SENSITIVE).'/i', $name)) {
                unset($meta[$name]);
            } elseif (is_array($value)) {
                $meta[$name] = $this->clean($value);
            }
        }

        return $meta;
    }
}
