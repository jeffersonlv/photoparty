<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL pública
    |--------------------------------------------------------------------------
    |
    | Base usada no QR code do convidado (/e/{slug}?t={token}) e no link do
    | telão. Fica separada de APP_URL para que o QR impresso aponte sempre
    | para o domínio de produção.
    |
    */

    'public_url' => rtrim(env('PHOTOPARTY_PUBLIC_URL', 'https://photo.uailabs.net'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Confirmação de e-mail no cadastro
    |--------------------------------------------------------------------------
    |
    | Desligada por enquanto (decisão de 16/11/2026). Com false, a conta nasce
    | verificada e nenhum e-mail é enviado. Para religar: PHOTOPARTY_REQUIRE_EMAIL_VERIFICATION=true.
    | Atenção: sem confirmação, a regra "um teste por e-mail verificado" fica mais fraca.
    |
    */

    'require_email_verification' => (bool) env('PHOTOPARTY_REQUIRE_EMAIL_VERIFICATION', false),

    /*
    |--------------------------------------------------------------------------
    | Teste grátis (SPEC seção 7)
    |--------------------------------------------------------------------------
    */

    'trial' => [
        // Período de teste da conta, contado a partir da publicação do evento de teste.
        'account_window_hours' => 3,
        // Duração máxima do evento de teste (ends_at <= starts_at + 2 h).
        'max_event_hours' => 2,
        // Limite de fotos por evento de teste.
        'max_photos' => 20,
        // Sem assinatura, as fotos são apagadas depois deste prazo.
        'retention_days' => 7,
    ],

    // Retenção usada quando a conta ativa não tem plano associado.
    'default_retention_days' => 30,

    // Limite de fotos por evento quando a conta ativa não tem plano.
    'default_max_photos_per_event' => 500,

    'locales' => ['pt_BR', 'en'],

    'filters' => ['none', 'bw', 'sepia', 'vivid', 'warm', 'cool'],

    'screen_defaults' => [
        'seconds_per_photo' => 6,
        'transition' => 'fade',
        'show_qr' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'register_per_hour' => (int) env('PHOTOPARTY_REGISTER_PER_HOUR', 5),
        'login_attempts' => 5,
    ],

];
