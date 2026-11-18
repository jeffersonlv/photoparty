<?php

// Módulos 3-4: página do convidado (servidor) e envio de fotos.
return [
    'page' => [
        'starts_at' => 'Este evento começa em :date. Volte nesse horário para enviar sua foto.',
        'ended' => 'Este evento já terminou.',
        'not_published' => 'Este evento ainda não foi publicado. Assim que o organizador publicar, você poderá enviar sua foto por este mesmo link.',
        'date_format' => 'd/m/Y \à\s H:i',
        'frame_date_format' => 'd/m/Y',
    ],

    'attributes' => [
        'photo' => 'foto',
        'thumb' => 'miniatura',
        'guest_name' => 'nome',
    ],

    'errors' => [
        'event_not_live' => 'Este evento não está recebendo fotos agora.',
        'event_limit' => 'Este evento atingiu o limite de fotos.',
        'guest_limit' => 'Você já enviou o máximo de fotos permitido neste evento.',
        'storage' => 'Não foi possível salvar a foto agora (espaço de armazenamento indisponível). Tente de novo mais tarde.',
        'not_image' => 'o arquivo não é uma imagem JPEG, PNG ou WebP válida.',
        'photo_dimensions' => 'a foto precisa ter 1080 × 1350 px (retrato) ou 1920 × 1080 px (paisagem).',
        'thumb_dimensions' => 'a miniatura precisa ter no máximo 400 px no lado maior e a mesma orientação da foto.',
        'frame' => 'essa moldura não está disponível neste evento.',
        'texts_format' => 'os textos enviados estão em formato inválido.',
        'texts_unknown' => 'essa moldura não aceita esse texto.',
        'texts_length' => 'cada texto pode ter no máximo :max caracteres.',
        'texts_html' => 'os textos não podem conter HTML nem caracteres especiais de controle.',
        'consent' => 'Para enviar a foto, marque que você leu e concorda com o aviso de privacidade.',
        'consent_version' => 'O aviso de privacidade foi atualizado. Recarregue a página, leia e aceite de novo.',
        'location_required' => 'Confirme de novo que você está no local do evento para enviar a foto.',
    ],
];
