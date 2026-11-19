<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\SiteSetting;

/**
 * Personalização global da lista de contas do admin (site_settings):
 * ordem das seções por status e limite por página de cada status.
 */
class AccountListSettings
{
    public const ORDER_KEY = 'admin.accounts.status_order';

    public const PER_PAGE_PREFIX = 'admin.accounts.per_page.';

    public const PER_PAGE_OPTIONS = [5, 10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 10;

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return array_map(fn (AccountStatus $s) => $s->value, AccountStatus::cases());
    }

    /**
     * @return list<string> permutação dos status válidos
     */
    public function order(): array
    {
        $all = self::statusValues();
        $stored = SiteSetting::query()->where('key', self::ORDER_KEY)->value('value');
        $order = array_values(array_unique(array_filter(
            explode(',', (string) $stored),
            fn ($s) => in_array($s, $all, true),
        )));

        // Completa com o que faltar (valor ausente ou antigo) na ordem padrão.
        return array_values(array_merge($order, array_diff($all, $order)));
    }

    /**
     * @return array<string, int>
     */
    public function perPage(): array
    {
        $stored = SiteSetting::query()->where('key', 'like', self::PER_PAGE_PREFIX.'%')->pluck('value', 'key')->all();
        $result = [];

        foreach (self::statusValues() as $status) {
            $value = (int) ($stored[self::PER_PAGE_PREFIX.$status] ?? 0);
            $result[$status] = in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : self::DEFAULT_PER_PAGE;
        }

        return $result;
    }

    /**
     * @param  list<string>  $order
     * @param  array<string, int|string>  $perPage
     */
    public function save(array $order, array $perPage): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::ORDER_KEY], ['value' => implode(',', $order)]);

        foreach (self::statusValues() as $status) {
            SiteSetting::query()->updateOrCreate(
                ['key' => self::PER_PAGE_PREFIX.$status],
                ['value' => (string) (int) $perPage[$status]],
            );
        }
    }
}
