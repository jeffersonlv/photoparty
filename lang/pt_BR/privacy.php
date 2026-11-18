<?php

// Formulário público de solicitação do titular (LGPD/GDPR) e e-mail de aviso ao operador.
return [
    'types' => [
        'deletion' => 'Excluir minhas fotos',
        'access' => 'Receber cópia das minhas fotos',
        'correction' => 'Corrigir dados (ex.: nome escrito na foto)',
        'other' => 'Outro pedido sobre meus dados',
    ],
    'form' => [
        'title' => 'Solicitação de privacidade',
        'intro' => 'Use este formulário para pedir acesso, cópia, correção ou exclusão de fotos que você enviou em um evento, inclusive depois que ele terminou. Durante o evento, você também pode fazer isso na própria página do evento, em "Minhas fotos".',
        'deadlines' => 'Respondemos em até :lgpd dias (LGPD) ou :gdpr dias (GDPR). O pedido é encaminhado ao organizador do evento, que é o controlador das fotos.',
        'type' => 'O que você quer pedir?',
        'event_link' => 'Link do evento',
        'event_link_hint' => 'Cole o link da página do evento (o endereço que abriu ao ler o QR code) ou escreva o nome e a data do evento.',
        'email' => 'Seu e-mail para contato',
        'email_hint' => 'Usado só para responder a este pedido.',
        'details' => 'Detalhes (opcional)',
        'details_hint' => 'Ajude a encontrar a foto: horário aproximado do envio, moldura usada, o que aparece na foto. Não envie documentos.',
        'confirm' => 'Declaro que o pedido é sobre fotos minhas (ou de quem eu sou responsável legal).',
        'confirm_attribute' => 'declaração',
        'submit' => 'Enviar solicitação',
        'sent' => 'Recebemos a sua solicitação. Vamos responder pelo e-mail informado dentro dos prazos acima.',
        'protocol' => 'Protocolo: :protocol',
        'policy_link' => 'Ler a política de privacidade',
    ],
    'mail' => [
        'subject' => 'Nova solicitação de privacidade (:protocol)',
        'body' => 'Uma nova solicitação de titular foi registrada (protocolo :protocol, tipo: :type). Veja os detalhes no admin:',
    ],
];
