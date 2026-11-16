<?php

namespace App\Services;

use App\Models\HomeSection;
use App\Models\Plan;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Monta o conteúdo da página inicial a partir de home_sections/home_items/site_settings.
 *
 * Cache de arquivo por idioma (Cache::rememberForever), limpo por forget() quando o
 * admin salva qualquer coisa. Os planos (pricing) são lidos ao vivo da tabela plans.
 */
class HomeContent
{
    public const CACHE_PREFIX = 'home.page.';

    /**
     * Seções ativas, em ordem, para o idioma, com fallback por campo para o idioma-base.
     *
     * @return array{sections: list<array<string, mixed>>, settings: array<string, string>}
     */
    public function forLocale(string $locale): array
    {
        $locale = $this->normalizeLocale($locale);

        return Cache::rememberForever(self::CACHE_PREFIX.$locale, fn () => $this->build($locale));
    }

    /**
     * Prévia: sem cache, opcionalmente com o conteúdo de uma seção substituído (não salvo).
     *
     * @param  array<string, string>|null  $contentOverride  campos da seção no idioma
     */
    public function preview(string $locale, ?string $sectionKey = null, ?array $contentOverride = null): array
    {
        $locale = $this->normalizeLocale($locale);

        return $this->build($locale, $sectionKey, $contentOverride, includeKey: $sectionKey);
    }

    public function forget(): void
    {
        foreach (config('photoparty.locales') as $locale) {
            Cache::forget(self::CACHE_PREFIX.$locale);
        }
    }

    /**
     * Planos públicos para a seção de preços (sempre ao vivo).
     */
    public function publicPlans()
    {
        return Plan::query()->where('is_public', true)->orderBy('sort_order')->orderBy('price')->get();
    }

    private function build(string $locale, ?string $overrideKey = null, ?array $override = null, ?string $includeKey = null): array
    {
        $sections = HomeSection::query()
            ->with(['items' => fn ($q) => $q->where('enabled', true)])
            ->when($includeKey === null, fn ($q) => $q->where('enabled', true))
            ->when($includeKey !== null, fn ($q) => $q->where(fn ($w) => $w->where('enabled', true)->orWhere('key', $includeKey)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $result = [];

        foreach ($sections as $section) {
            $content = $overrideKey === $section->key && $override !== null
                ? $this->resolveFlat($override, $section->content ?? [], $locale)
                : $this->resolve($section->content ?? [], $locale);

            $result[] = [
                'key' => $section->key,
                'content' => $content,
                'items' => $section->items->map(fn ($item) => $this->resolve($item->data ?? [], $locale))->all(),
            ];
        }

        return [
            'sections' => $result,
            'settings' => $this->settings($locale),
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $byLocale
     * @return array<string, string>
     */
    private function resolve(array $byLocale, string $locale): array
    {
        $base = (array) config('photoparty.locales');
        $order = array_unique([$locale, ...$base]);
        $out = [];

        foreach ($order as $candidate) {
            foreach ((array) ($byLocale[$candidate] ?? []) as $field => $value) {
                if (is_string($value) && $value !== '' && ! isset($out[$field])) {
                    $out[$field] = $value;
                }
            }
        }

        return $out;
    }

    private function resolveFlat(array $flat, array $stored, string $locale): array
    {
        $stored[$locale] = array_merge((array) ($stored[$locale] ?? []), $flat);

        return $this->resolve($stored, $locale);
    }

    /**
     * @return array<string, string>
     */
    private function settings(string $locale): array
    {
        $all = SiteSetting::allKeyed();
        $out = [];

        foreach (config('home_sections.settings') as $key => $def) {
            $value = $def['per_locale']
                ? ($all["{$key}_{$locale}"] ?? null)
                : ($all[$key] ?? null);

            if (is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function normalizeLocale(string $locale): string
    {
        return in_array($locale, config('photoparty.locales'), true) ? $locale : config('photoparty.locales')[0];
    }
}
