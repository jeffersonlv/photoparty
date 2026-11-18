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

    /*
    |--------------------------------------------------------------------------
    | Filtros do convidado (lista canônica)
    |--------------------------------------------------------------------------
    |
    | Fonte única: validação do upload, filtros ativos do evento, payload da página do
    | convidado e tela de evento leem daqui (App\Support\GuestFilters). O front
    | (resources/js/guest/effects.js, FILTERS) usa exatamente estas chaves; o teste
    | tests/js/effects.test.js confere os dois lados.
    |
    | 'sepia' NÃO é filtro do convidado: é só o EFEITO da moldura wanted_poster. Clientes e
    | eventos antigos que ainda mandam/guardam 'sepia' como filtro são aceitos por compatibilidade
    | e normalizados no servidor para 'vintage' (filter_aliases).
    |
    */

    'filters' => ['none', 'bw', 'vintage', 'vivid', 'cool', 'warm'],

    'filter_aliases' => ['sepia' => 'vintage'],

    'screen_defaults' => [
        'seconds_per_photo' => 6,
        'transition' => 'fade',
        'show_qr' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacidade (LGPD / GDPR)
    |--------------------------------------------------------------------------
    |
    | consent_version: versão do texto de consentimento do convidado. O servidor recusa
    | (422) envio sem consent=1 e com versão diferente desta; a versão aceita fica em
    | photos.consent_version. legal_version: versão de /privacidade e /termos.
    | Mudou o texto? Mude a data (os convidados verão o aviso de novo).
    |
    | privacy_contact_email: canal do titular. Vazio => as páginas mostram "[a preencher]"
    | e o admin vê um aviso. Pode ser sobrescrito em /admin/privacy/settings.
    |
    | legal_reviewed: false => as páginas legais mostram a nota discreta "modelo pendente
    | de revisão jurídica" para todos (e um aviso em destaque para o admin). Depois da
    | revisão por advogado, defina PHOTOPARTY_LEGAL_REVIEWED=true.
    |
    */

    'privacy_contact_email' => (string) env('PHOTOPARTY_PRIVACY_CONTACT_EMAIL', ''),

    'privacy' => [
        // 2026-11-18.2: aviso e políticas ganharam a parte de geolocalização (envio só no local).
        'consent_version' => '2026-11-18.2',
        'legal_version' => '2026-11-18.2',
        'legal_reviewed' => (bool) env('PHOTOPARTY_LEGAL_REVIEWED', false),
        // Cookie anônimo do aparelho do convidado (pp_device).
        'device_cookie_days' => 30,
        // audit_logs: IP apagado depois deste prazo.
        'audit_ip_retention_days' => 90,
        // Registro "excluída pelo convidado" (só para o telão retirar a foto) vive este tempo.
        'tombstone_hours' => 24,
        // Prazos de resposta exibidos ao titular e no admin.
        'response_days' => ['lgpd' => 15, 'gdpr' => 30],
        // Formulário público de solicitação: envios por hora por IP.
        'requests_per_hour' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocalização: "envio só no local do evento" (recurso de plano)
    |--------------------------------------------------------------------------
    |
    | Account::canUseGeofence() = conta active e plano com allows_geofence. Raio em metros.
    | A posição do convidado só é usada para calcular a distância no servidor; nunca é gravada
    | (banco, log ou sessão). Aceita se distância - min(precisão, max_accuracy_m) <= raio.
    | Recusa precisão pior que max_accuracy_m e leitura com mais de max_age_seconds.
    | Aceito => "passe de local" na sessão por pass_minutes (vinculado ao aparelho e ao evento).
    |
    */

    'geofence' => [
        'radius_default' => 100,
        'radius_min' => 50,
        'radius_max' => 2000,
        'max_accuracy_m' => 150,
        'max_age_seconds' => 120,
        'pass_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demonstração ao vivo (página inicial)
    |--------------------------------------------------------------------------
    |
    | Evento do sistema (events.is_demo) numa conta interna, criado por DemoSeeder. Sempre ao vivo,
    | moderação pós, sem geolocalização. Fotos aparecem publicamente na home e o comando
    | demo:reset (de hora em hora) apaga TODAS as fotos e arquivos. Limites contra abuso:
    | max_photos_per_hour no evento (servidor) e ip_uploads_per_hour por IP.
    |
    */

    'demo' => [
        'slug' => 'demo-ao-vivo',
        'account_name' => 'Photo Party Demo',
        'max_photos_per_hour' => 30,
        'ip_uploads_per_hour' => 6,
        // Fotos mais recentes no mini-telão da home.
        'recent_photos' => 8,
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
