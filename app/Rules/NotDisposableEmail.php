<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Bloqueia e-mails de domínios temporários (lista local em config/disposable_email_domains.php).
 * Também bloqueia subdomínios: "a.mailinator.com" casa com "mailinator.com".
 */
class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        if (static::isDisposable($value)) {
            $fail('app.errors.disposable_email')->translate();
        }
    }

    public static function isDisposable(string $email): bool
    {
        $domain = Str::lower(trim(Str::afterLast($email, '@')));
        $domain = rtrim($domain, '.');
        $blocked = array_map('strtolower', (array) config('disposable_email_domains', []));

        $parts = explode('.', $domain);

        while (count($parts) >= 2) {
            if (in_array(implode('.', $parts), $blocked, true)) {
                return true;
            }

            array_shift($parts);
        }

        return false;
    }
}
