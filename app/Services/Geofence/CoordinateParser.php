<?php

namespace App\Services\Geofence;

/**
 * Interpreta o local colado pelo organizador, SEM nenhuma chamada externa:
 *
 *  - coordenadas "lat, lng" (também "lat lng" ou "lat;lng");
 *  - links do Google Maps com o marcador do lugar "!3d{lat}!4d{lng}" (preferido: é o ponto
 *    do lugar, não o centro da tela);
 *  - "?q=lat,lng", "?query=lat,lng", "&ll=lat,lng", "?destination=lat,lng";
 *  - "/@lat,lng,zoom" (centro do mapa) e "/place/lat,lng".
 *
 * Links curtos (maps.app.goo.gl, goo.gl/maps, g.co) só se resolvem seguindo o redirecionamento,
 * o que exigiria chamada externa: recusados com o motivo "short_link".
 */
final class CoordinateParser
{
    public const SHORT_LINK = 'short_link';

    public const INVALID = 'invalid';

    private const NUM = '[-+]?\d{1,3}(?:\.\d+)?';

    /**
     * @return array{lat: float, lng: float}
     *
     * @throws InvalidCoordinates
     */
    public function parse(string $input): array
    {
        $text = trim($input);

        if ($text === '' || mb_strlen($text) > 2000) {
            throw new InvalidCoordinates(self::INVALID);
        }

        if (preg_match('#^(?:https?://)?(?:maps\.app\.goo\.gl|goo\.gl/maps|g\.co/kgs|g\.page)(?:/|$)#i', $text)) {
            throw new InvalidCoordinates(self::SHORT_LINK);
        }

        $decoded = rawurldecode(str_replace('+', ' ', $text));
        $n = self::NUM;

        $patterns = [
            // marcador do lugar (data=...!3d-23.5!4d-46.6)
            "#!3d({$n})!4d({$n})#",
            // parâmetros de consulta com "lat,lng"
            "#[?&](?:q|query|ll|sll|destination|daddr|center)=\s*(?:loc:)?\s*({$n})\s*,\s*({$n})(?![\d.])#i",
            // /@lat,lng,17z
            "#/@({$n}),({$n})(?:,|$|/|\?)#",
            // /place/lat,lng ou /search/lat,lng
            "#/(?:place|search|dir)/({$n})\s*,\s*({$n})(?![\d.])#i",
            // texto puro "lat, lng" / "lat lng" / "lat;lng"
            "#^\s*({$n})\s*[,; ]\s*({$n})\s*$#",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $m)) {
                return $this->coordinates($m[1], $m[2]);
            }
        }

        throw new InvalidCoordinates(self::INVALID);
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public function coordinates(string|float|int $lat, string|float|int $lng): array
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidCoordinates(self::INVALID);
        }

        // "0, 0" quase sempre é campo vazio/erro, não um evento no Golfo da Guiné.
        if ($lat == 0.0 && $lng == 0.0) {
            throw new InvalidCoordinates(self::INVALID);
        }

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }
}
