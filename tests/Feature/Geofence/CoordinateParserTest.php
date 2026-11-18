<?php

use App\Services\Geofence\CoordinateParser;
use App\Services\Geofence\InvalidCoordinates;

/*
| Local colado pelo organizador: interpretado só no servidor, sem chamadas externas.
*/

it('parses common Google Maps links and plain coordinates', function (string $input, float $lat, float $lng) {
    expect((new CoordinateParser())->parse($input))->toBe(['lat' => $lat, 'lng' => $lng]);
})->with([
    'lat, lng' => ['-23.561414, -46.655881', -23.561414, -46.655881],
    'lat,lng sem espaço' => ['51.8985,-8.4756', 51.8985, -8.4756],
    'lat lng' => ['51.898500 -8.475600', 51.8985, -8.4756],
    'mais casas (arredonda a 6)' => ['-23.56141412345, -46.6558811111', -23.561414, -46.655881],
    '@lat,lng,zoom' => ['https://www.google.com/maps/@-23.5614141,-46.6558811,17z', -23.561414, -46.655881],
    '?q=lat,lng' => ['https://maps.google.com/?q=-23.561414,-46.655881', -23.561414, -46.655881],
    '?q= com %2C' => ['https://www.google.com/maps?q=-23.561414%2C-46.655881&z=17', -23.561414, -46.655881],
    'search api query=' => ['https://www.google.com/maps/search/?api=1&query=51.8985,-8.4756', 51.8985, -8.4756],
    '!3d!4d tem prioridade sobre @' => [
        'https://www.google.com/maps/place/Parque+Ibirapuera/@-23.58,-46.66,15z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d-23.5874162!4d-46.6576336',
        -23.587416, -46.657634,
    ],
    '/place/lat,lng' => ['https://www.google.com/maps/place/51.8985,-8.4756', 51.8985, -8.4756],
    '?ll=' => ['https://maps.google.com/maps?ll=51.8985,-8.4756&z=16', 51.8985, -8.4756],
]);

it('rejects short links that would need an external call', function (string $input) {
    try {
        (new CoordinateParser())->parse($input);
        $this->fail('should throw');
    } catch (InvalidCoordinates $e) {
        expect($e->reason)->toBe(CoordinateParser::SHORT_LINK);
    }
})->with([
    'https://maps.app.goo.gl/AbCdEf123',
    'maps.app.goo.gl/xyz',
    'https://goo.gl/maps/AbCdEf',
]);

it('rejects invalid coordinates and unknown text', function (string $input) {
    try {
        (new CoordinateParser())->parse($input);
        $this->fail('should throw');
    } catch (InvalidCoordinates $e) {
        expect($e->reason)->toBe(CoordinateParser::INVALID);
    }
})->with([
    'latitude > 90' => ['91.5, 10'],
    'longitude > 180' => ['10, 181'],
    'zero zero' => ['0, 0'],
    'texto' => ['Rua das Flores, 123'],
    'vazio' => ['   '],
    'link sem coordenadas' => ['https://www.google.com/maps/place/Parque+Ibirapuera'],
    'um número só' => ['-23.56'],
]);
