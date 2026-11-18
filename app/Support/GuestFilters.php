<?php

namespace App\Support;

use App\Models\Event;

/**
 * Lista canônica dos filtros do convidado (config photoparty.filters) e compatibilidade
 * com a chave antiga 'sepia' (alias de 'vintage'; photoparty.filter_aliases).
 *
 * 'sepia' continua existindo apenas como EFEITO de moldura, nunca como filtro do convidado.
 */
final class GuestFilters
{
    /** @return list<string> */
    public static function all(): array
    {
        return array_values((array) config('photoparty.filters'));
    }

    /** @return array<string, string> alias => chave canônica */
    public static function aliases(): array
    {
        return (array) config('photoparty.filter_aliases', []);
    }

    /** Chaves aceitas na entrada: canônicas + aliases de clientes antigos. */
    public static function accepted(): array
    {
        return [...self::all(), ...array_keys(self::aliases())];
    }

    /** Converte alias em chave canônica; null/vazio => 'none'. Chave desconhecida volta como veio. */
    public static function normalize(?string $key): string
    {
        $key = $key === null || $key === '' ? 'none' : $key;

        return self::aliases()[$key] ?? $key;
    }

    /**
     * Normaliza uma lista (aliases viram canônicos), descarta desconhecidos e duplicados e
     * devolve na ordem canônica.
     *
     * @param  array<int, mixed>  $keys
     * @return list<string>
     */
    public static function normalizeList(array $keys): array
    {
        $wanted = array_map(fn ($k) => self::normalize(is_string($k) ? $k : null), $keys);

        return array_values(array_intersect(self::all(), $wanted));
    }

    /**
     * Filtros ativos do evento para o convidado: sempre inclui 'none'. Evento sem a lista
     * gravada (null, eventos antigos) oferece todos.
     *
     * @return list<string>
     */
    public static function enabledFor(Event $event): array
    {
        $stored = $event->enabled_filters;

        if (! is_array($stored)) {
            return self::all();
        }

        return array_values(array_unique(['none', ...self::normalizeList($stored)]));
    }
}
