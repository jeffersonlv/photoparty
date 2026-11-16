<?php

/*
|--------------------------------------------------------------------------
| Schema das seções da página inicial
|--------------------------------------------------------------------------
|
| Cada seção define campos de texto simples (sem HTML livre): tudo é escapado
| na saída. type: text (uma linha) | textarea (várias linhas). Links dos botões
| são fixos no código (cadastro/âncoras), nunca editáveis, para evitar
| javascript: ou redirecionamentos maliciosos.
|
| "items": itens repetíveis da seção (null = a seção não tem itens).
| Os rótulos dos campos vêm de lang/{locale}/admin.php (admin.home.fields.{campo}).
|
*/

return [

    // Ordem inicial das seções (usada pelo seeder).
    'order' => ['hero', 'how_it_works', 'audiences', 'features', 'pricing', 'faq', 'final_cta'],

    'sections' => [
        'hero' => [
            'fields' => [
                'eyebrow' => ['type' => 'text', 'max' => 80],
                'title' => ['type' => 'text', 'max' => 160, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 400, 'required' => true],
                'cta_primary' => ['type' => 'text', 'max' => 40, 'required' => true],
                'cta_secondary' => ['type' => 'text', 'max' => 40],
                'demo_note' => ['type' => 'text', 'max' => 120],
            ],
            'items' => null,
        ],
        'how_it_works' => [
            'fields' => [
                'title' => ['type' => 'text', 'max' => 120, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 300],
            ],
            'items' => [
                'max' => 6,
                'fields' => [
                    'title' => ['type' => 'text', 'max' => 80, 'required' => true],
                    'text' => ['type' => 'textarea', 'max' => 300, 'required' => true],
                ],
            ],
        ],
        'audiences' => [
            'fields' => [
                'title' => ['type' => 'text', 'max' => 120, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 300],
            ],
            'items' => [
                'max' => 8,
                'fields' => [
                    'title' => ['type' => 'text', 'max' => 80, 'required' => true],
                    'text' => ['type' => 'textarea', 'max' => 300, 'required' => true],
                ],
            ],
        ],
        'features' => [
            'fields' => [
                'title' => ['type' => 'text', 'max' => 120, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 300],
            ],
            'items' => [
                'max' => 12,
                'fields' => [
                    'title' => ['type' => 'text', 'max' => 80, 'required' => true],
                    'text' => ['type' => 'textarea', 'max' => 300, 'required' => true],
                ],
            ],
        ],
        'pricing' => [
            // Os planos vêm da tabela plans (is_public); aqui só os textos ao redor.
            'fields' => [
                'title' => ['type' => 'text', 'max' => 120, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 300],
                'cta' => ['type' => 'text', 'max' => 40, 'required' => true],
                'note' => ['type' => 'textarea', 'max' => 300],
            ],
            'items' => null,
        ],
        'faq' => [
            'fields' => [
                'title' => ['type' => 'text', 'max' => 120, 'required' => true],
            ],
            'items' => [
                'max' => 20,
                'fields' => [
                    'question' => ['type' => 'text', 'max' => 160, 'required' => true],
                    'answer' => ['type' => 'textarea', 'max' => 800, 'required' => true],
                ],
            ],
        ],
        'final_cta' => [
            'fields' => [
                'title' => ['type' => 'text', 'max' => 160, 'required' => true],
                'subtitle' => ['type' => 'textarea', 'max' => 300],
                'cta' => ['type' => 'text', 'max' => 40, 'required' => true],
            ],
            'items' => null,
        ],
    ],

    // Campos de site_settings editáveis no admin.
    'settings' => [
        'seo_title' => ['per_locale' => true, 'max' => 70],
        'seo_description' => ['per_locale' => true, 'max' => 170],
        'contact_email' => ['per_locale' => false, 'max' => 120, 'email' => true],
        'contact_phone' => ['per_locale' => false, 'max' => 40],
    ],
];
