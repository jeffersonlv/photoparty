<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Dados de identificação do operador e do encarregado (DPO) usados nas páginas legais.
 *
 * Fonte: site_settings (editável em /admin/privacy/settings); o e-mail de contato cai para
 * config('photoparty.privacy_contact_email'). Nada é inventado: campo vazio => "[a preencher]"
 * nas páginas e um aviso visível só para o admin.
 */
class PrivacySettings
{
    /** Campos em site_settings (chave => [max, email?, multiline?]). */
    public const FIELDS = [
        'privacy_company_name' => ['max' => 160],
        'privacy_company_id' => ['max' => 60],
        'privacy_company_address' => ['max' => 300],
        'privacy_contact_email' => ['max' => 120, 'email' => true],
        'privacy_dpo_name' => ['max' => 120],
        'privacy_dpo_email' => ['max' => 120, 'email' => true],
        'privacy_hosting_region' => ['max' => 160],
        'privacy_transfer_note' => ['max' => 600, 'multiline' => true],
    ];

    /** Campos que precisam estar preenchidos antes do uso comercial. */
    public const REQUIRED = [
        'privacy_company_name',
        'privacy_contact_email',
        'privacy_dpo_name',
        'privacy_hosting_region',
    ];

    /**
     * @return array<string, string> valores crus ('' quando vazio)
     */
    public function values(): array
    {
        $stored = SiteSetting::query()->whereIn('key', array_keys(self::FIELDS))->pluck('value', 'key')->all();
        $values = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = trim((string) ($stored[$key] ?? ''));
        }

        if ($values['privacy_contact_email'] === '') {
            $values['privacy_contact_email'] = trim((string) config('photoparty.privacy_contact_email', ''));
        }

        return $values;
    }

    /**
     * Valores para exibir: vazio => "[a preencher]" / "[to be completed]".
     *
     * @return array<string, string>
     */
    public function display(): array
    {
        $placeholder = __('legal.missing');

        return array_map(fn (string $v) => $v === '' ? $placeholder : $v, $this->values());
    }

    /**
     * @return list<string> campos obrigatórios ainda vazios
     */
    public function missing(): array
    {
        $values = $this->values();

        return array_values(array_filter(self::REQUIRED, fn (string $key) => $values[$key] === ''));
    }

    public function contactEmail(): string
    {
        return $this->values()['privacy_contact_email'];
    }
}
