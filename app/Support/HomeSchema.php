<?php

namespace App\Support;

/**
 * Regras de validação e extração de conteúdo a partir do schema em config/home_sections.php.
 * Só campos do schema são aceitos; tudo é texto puro (escapado na saída).
 */
class HomeSchema
{
    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, list<string>>
     */
    public static function rules(array $fields, string $prefix): array
    {
        $rules = [$prefix => ['required', 'array']];

        foreach (config('photoparty.locales') as $locale) {
            $rules["{$prefix}.{$locale}"] = ['required', 'array'];

            foreach ($fields as $name => $def) {
                $rules["{$prefix}.{$locale}.{$name}"] = [
                    ! empty($def['required']) ? 'required' : 'nullable',
                    'string',
                    'max:'.($def['max'] ?? 255),
                ];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    public static function attributes(array $fields, string $prefix): array
    {
        $attributes = [];

        foreach (config('photoparty.locales') as $locale) {
            foreach ($fields as $name => $def) {
                $attributes["{$prefix}.{$locale}.{$name}"] = __("admin.home.fields.{$name}").' ('.self::localeLabel($locale).')';
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input  valor já validado: [locale => [campo => texto]]
     * @return array<string, array<string, string>>
     */
    public static function extract(array $fields, array $input): array
    {
        $out = [];

        foreach (config('photoparty.locales') as $locale) {
            foreach (array_keys($fields) as $name) {
                $value = $input[$locale][$name] ?? null;
                $out[$locale][$name] = is_string($value) ? trim($value) : '';
            }
        }

        return $out;
    }

    public static function localeLabel(string $locale): string
    {
        return $locale === 'pt_BR' ? 'PT-BR' : strtoupper($locale);
    }
}
