<?php

it('sends the security headers', function (string $uri) {
    $response = $this->get($uri);

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("script-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('unsafe-eval')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=(self)')
        ->and($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)')
        ->and($response->headers->get('Permissions-Policy'))->not->toContain('geolocation=()');
})->with([
    'login page' => '/login',
    'not found page' => '/esta-pagina-nao-existe',
]);
