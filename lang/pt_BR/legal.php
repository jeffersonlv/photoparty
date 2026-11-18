<?php

// Política de privacidade e termos de uso (LGPD + GDPR). Texto-modelo: precisa de revisão
// por advogado antes do uso comercial (ver README, "Privacidade (LGPD/GDPR)").
// Placeholders (preenchidos por LegalController a partir de site_settings/config):
// :company :company_id :address :contact :dpo :dpo_email :region :transfer :trial_days
// :lgpd_days :gdpr_days :cookie_days :ip_days :session_minutes :request_url :privacy_url :version
return [
    'missing' => '[a preencher]',
    'version' => 'Versão :version',
    'back' => 'Voltar para a página inicial',
    'public_note' => 'Texto-modelo pendente de revisão jurídica.',
    'admin_banner' => 'Este texto é um modelo e deve ser revisado por um advogado antes do uso comercial.',
    'admin_missing' => 'Campos de privacidade a preencher no admin: :fields.',
    'admin_settings_link' => 'Preencher agora',
    'on_this_page' => 'Nesta página',
    'transfer_default' => 'Os dados ficam nos servidores da hospedagem indicados na seção 5. Se a região contratada estiver fora do país do titular (por exemplo, servidores na União Europeia para titulares no Brasil, ou o contrário), essa transferência é necessária para prestar o serviço.',

    'cookies_table' => [
        'name' => 'Nome',
        'purpose' => 'Finalidade',
        'duration' => 'Duração',
        'rows' => [
            ['name' => ':session_cookie', 'purpose' => 'Sessão do painel do organizador e da página do convidado (login, idioma, proteção do formulário).', 'duration' => ':session_minutes minutos de inatividade'],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'Proteção contra falsificação de requisições (CSRF).', 'duration' => ':session_minutes minutos'],
            ['name' => 'remember_web_…', 'purpose' => 'Mantém o organizador conectado, apenas se ele marcar "Lembrar de mim".', 'duration' => 'até 400 dias ou até sair'],
            ['name' => 'pp_device', 'purpose' => 'Identificador anônimo e aleatório do aparelho do convidado, para limitar envios e mostrar "Minhas fotos". No servidor guardamos só um hash.', 'duration' => ':cookie_days dias'],
            ['name' => 'pp_consent_v1:… (armazenamento local)', 'purpose' => 'Lembra, no próprio aparelho, que o convidado já leu e aceitou o aviso desta versão.', 'duration' => 'até o convidado limpar os dados do navegador'],
        ],
    ],

    'privacy' => [
        'title' => 'Política de privacidade',
        'intro' => 'Esta política explica como o Photo Party trata dados pessoais, em conformidade com a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018) e com o Regulamento Geral sobre a Proteção de Dados da União Europeia (GDPR, Regulamento (UE) 2016/679).',
        'sections' => [
            [
                'id' => 'quem-somos',
                'heading' => '1. Quem somos e como falar conosco',
                'paragraphs' => [
                    'O Photo Party é operado por :company (registro: :company_id; endereço: :address).',
                    'Canal de privacidade: :contact. Encarregado de proteção de dados (DPO): :dpo (:dpo_email).',
                ],
            ],
            [
                'id' => 'papeis',
                'heading' => '2. Papéis: controlador e operador',
                'paragraphs' => [
                    'Fotos e dados dos convidados de um evento: o controlador é o organizador do evento (a conta que criou o evento e cujo nome aparece no aviso da página do convidado). Ele decide para que as fotos são usadas. O Photo Party é o operador (processador, no GDPR): trata essas fotos apenas para prestar o serviço ao organizador e conforme as instruções dele.',
                    'Dados das contas de organizadores (cadastro, login, uso do painel): o Photo Party é o controlador.',
                ],
            ],
            [
                'id' => 'dados',
                'heading' => '3. Dados que tratamos',
                'items' => [
                    'Convidados: a foto enviada (já composta com moldura e filtro), a miniatura, o nome e os textos opcionais que o convidado digitar na moldura, a data e hora do envio, a versão do aviso aceito e um identificador anônimo do aparelho (hash de um cookie aleatório). Não pedimos e-mail, telefone ou conta. Os metadados das imagens (EXIF, inclusive localização GPS) são removidos no servidor.',
                    'Organizadores: nome, e-mail, senha (guardada apenas como hash), dados da conta e dos eventos, molduras enviadas e registros de auditoria de ações no painel (inclusive endereço IP).',
                    'Solicitações de titulares: link do evento, e-mail de contato e a descrição do pedido.',
                    'Registros técnicos: endereço IP e navegador na sessão e nos registros de segurança, pelos prazos da seção 7.',
                    'Não tratamos dados sensíveis de propósito e não usamos reconhecimento facial, biometria nem perfilamento. Uma foto pode mostrar características de uma pessoa; ela é tratada apenas como imagem do evento.',
                ],
            ],
            [
                'id' => 'finalidades',
                'heading' => '4. Finalidades e bases legais',
                'items' => [
                    'Exibir a foto no telão do evento e entregá-la ao organizador (galeria e download): consentimento do convidado (LGPD art. 7º, I; GDPR art. 6(1)(a)), coletado na página do evento antes do primeiro envio.',
                    'Prestar o serviço ao organizador (conta, eventos, moderação, exportação): execução de contrato (LGPD art. 7º, V; GDPR art. 6(1)(b)).',
                    'Segurança, prevenção de abuso e auditoria (limites de envio, registros de acesso): legítimo interesse e cumprimento de obrigação legal (LGPD art. 7º, II e IX; GDPR art. 6(1)(c) e (f)).',
                    'Atender pedidos de titulares: cumprimento de obrigação legal (LGPD art. 18; GDPR arts. 12 a 22).',
                ],
            ],
            [
                'id' => 'compartilhamento',
                'heading' => '5. Compartilhamento e suboperadores',
                'paragraphs' => [
                    'Não vendemos nem compartilhamos dados com terceiros para publicidade. Não usamos serviços de análise, redes de anúncios, CDNs, fontes externas ou captcha de terceiros.',
                    'As fotos de um evento são vistas pelo organizador e pela equipe dele e exibidas no telão do evento. Cada organizador só acessa os próprios eventos.',
                    'Suboperador: a hospedagem do site e do banco de dados é contratada da Hostinger, com servidores em :region. O e-mail transacional (quando ativado) usa o servidor de e-mail da mesma hospedagem.',
                    'Podemos fornecer dados a autoridades quando a lei exigir.',
                ],
            ],
            [
                'id' => 'transferencia',
                'heading' => '6. Transferência internacional',
                'paragraphs' => [
                    ':transfer',
                    'Quando houver transferência para fora do Brasil ou do Espaço Econômico Europeu, ela se apoia nos mecanismos previstos na LGPD (art. 33) e no GDPR (capítulo V), como decisões de adequação ou cláusulas contratuais padrão.',
                ],
            ],
            [
                'id' => 'retencao',
                'heading' => '7. Por quanto tempo guardamos',
                'items' => [
                    'Fotos, miniaturas, prévias e arquivos ZIP de um evento: até o fim do prazo de retenção do plano do organizador, contado do fim do evento (no teste grátis encerrado sem assinatura, :trial_days dias após o fim do período de teste). Depois disso, um processo diário apaga os arquivos e mantém só o registro mínimo do evento (nome e datas) no painel do organizador.',
                    'Foto excluída pelo próprio convidado: os arquivos são apagados na hora; um registro técnico sem dados pessoais existe por algumas horas apenas para retirá-la do telão.',
                    'Cookie do aparelho do convidado: :cookie_days dias.',
                    'Endereço IP nos registros de auditoria: :ip_days dias; depois disso o IP é apagado.',
                    'Dados da conta do organizador: enquanto a conta existir. Ao excluir a conta, tudo é apagado (banco de dados e arquivos), salvo um registro de auditoria sem dados pessoais.',
                    'Solicitações de titulares: pelo tempo necessário para comprovar o atendimento.',
                ],
            ],
            [
                'id' => 'direitos',
                'heading' => '8. Seus direitos',
                'paragraphs' => [
                    'Pela LGPD (art. 18) você pode pedir: confirmação da existência de tratamento; acesso; correção; anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos; portabilidade; eliminação dos dados tratados com consentimento; informação sobre compartilhamento; informação sobre a possibilidade de não consentir; e revogação do consentimento.',
                    'Pelo GDPR (arts. 15 a 22) você tem direito de acesso, retificação, apagamento, limitação do tratamento, portabilidade, oposição e de não ficar sujeito a decisões exclusivamente automatizadas (não fazemos decisões automatizadas nem perfilamento). A retirada do consentimento não afeta o tratamento feito antes dela.',
                ],
            ],
            [
                'id' => 'como-exercer',
                'heading' => '9. Como exercer seus direitos e prazos',
                'items' => [
                    'Durante o evento, na própria página do evento, em "Minhas fotos": ver, baixar em tamanho original e excluir as fotos enviadas do seu aparelho. Excluir a foto revoga o consentimento e a retira do telão.',
                    'Depois do evento ou de outro aparelho: use o formulário em :request_url informando o link do evento e um e-mail de contato. Como o convidado não tem conta, podemos pedir informações para confirmar que a foto é sua antes de entregá-la ou apagá-la.',
                    'Organizadores: em "Minha conta", exportar os próprios dados (JSON) e excluir a conta com todos os dados.',
                    'Prazos de resposta: até :lgpd_days dias (LGPD) e até :gdpr_days dias (GDPR), prorrogáveis nos casos previstos em lei. Como operador, encaminhamos ao organizador (controlador) os pedidos sobre fotos dos eventos dele e o ajudamos a responder.',
                ],
            ],
            [
                'id' => 'autoridade',
                'heading' => '10. Autoridade de controle',
                'paragraphs' => [
                    'Se não ficar satisfeito com a nossa resposta, você pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD), no Brasil, ou à autoridade de controle do seu país na União Europeia (na Irlanda, a Data Protection Commission).',
                ],
            ],
            [
                'id' => 'seguranca',
                'heading' => '11. Segurança',
                'paragraphs' => [
                    'Conexão sempre criptografada (HTTPS), fotos fora da pasta pública e entregues só a quem tem permissão, isolamento entre organizadores, links do telão temporários, senhas com hash, limites de tentativas, remoção de metadados das imagens e registros de auditoria. Nenhuma medida é infalível; por isso mantemos o plano de incidentes da seção 13.',
                ],
            ],
            [
                'id' => 'menores',
                'heading' => '12. Crianças e adolescentes',
                'paragraphs' => [
                    'Eventos podem ter participantes menores de idade. O organizador, como controlador, é responsável por obter a autorização dos pais ou responsáveis para coletar e exibir imagens de menores (LGPD art. 14; GDPR art. 8) e declara isso ao criar cada evento. O Photo Party não verifica idade. Pais ou responsáveis podem pedir a exclusão de uma foto pelo formulário da seção 9.',
                ],
            ],
            [
                'id' => 'incidentes',
                'heading' => '13. Incidentes de segurança',
                'paragraphs' => [
                    'Se ocorrer um incidente que possa causar risco ou dano relevante, avisaremos sem demora injustificada os organizadores afetados (controladores) e, quando formos o controlador ou quando a lei exigir, a ANPD e/ou a autoridade de controle competente (no GDPR, em até 72 horas) e os titulares afetados.',
                ],
            ],
            [
                'id' => 'cookies',
                'heading' => '14. Cookies',
                'paragraphs' => [
                    'Usamos apenas cookies estritamente necessários para o funcionamento do serviço. Por isso não há banner de consentimento de cookies. Não usamos cookies de publicidade, de análise ou de terceiros.',
                ],
                'cookies' => true,
            ],
            [
                'id' => 'alteracoes',
                'heading' => '15. Alterações desta política',
                'paragraphs' => [
                    'Esta política tem versão e data. Quando ela mudar de forma relevante, a nova versão é publicada aqui e o aviso da página do convidado passa a pedir um novo aceite; organizadores são avisados pelo painel ou por e-mail.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Termos de uso',
        'intro' => 'Estes termos regem o uso do Photo Party por organizadores de eventos (titulares de conta) e por convidados que enviam fotos.',
        'sections' => [
            [
                'id' => 'servico',
                'heading' => '1. O serviço',
                'paragraphs' => [
                    'O Photo Party, operado por :company (:company_id, :address), permite que organizadores criem eventos em que convidados enviam fotos pelo navegador (via QR code) para exibição em um telão, com moderação e galeria para o organizador.',
                ],
            ],
            [
                'id' => 'conta',
                'heading' => '2. Conta do organizador',
                'paragraphs' => [
                    'O organizador deve fornecer dados verdadeiros, manter a senha em sigilo e é responsável pelo que acontece na conta e pelos membros da equipe que convidar. Uma conta de teste grátis por pessoa.',
                ],
            ],
            [
                'id' => 'planos',
                'heading' => '3. Planos, teste grátis e cancelamento',
                'paragraphs' => [
                    'Os limites (eventos, fotos, retenção, molduras personalizadas) dependem do plano. No teste grátis o organizador vê apenas prévias de baixa resolução; o download em tamanho original e o ZIP ficam disponíveis nos planos pagos. A ativação de planos é feita pela administração enquanto não houver pagamento online. O organizador pode excluir a conta a qualquer momento em "Minha conta".',
                ],
            ],
            [
                'id' => 'uso',
                'heading' => '4. Uso aceitável e moderação',
                'paragraphs' => [
                    'É proibido usar o serviço para conteúdo ilegal, ofensivo, discriminatório, que viole direitos de imagem ou autorais, ou que exponha menores de forma inadequada. O organizador modera o que vai ao telão e responde pelo conteúdo exibido no seu evento. Podemos suspender contas que violem estes termos.',
                ],
            ],
            [
                'id' => 'convidados',
                'heading' => '5. Convidados',
                'paragraphs' => [
                    'O convidado envia fotos voluntariamente, depois de ler o aviso de privacidade do evento, e declara ter o direito de enviá-las. Ele pode ver, baixar e excluir as próprias fotos em "Minhas fotos" enquanto o evento estiver ativo e pedir acesso ou exclusão depois pelo formulário de privacidade.',
                ],
            ],
            [
                'id' => 'dpa',
                'heading' => '6. Tratamento de dados em nome do organizador (acordo de tratamento de dados)',
                'paragraphs' => [
                    'Quanto às fotos e dados dos convidados dos seus eventos, o organizador é o controlador e o Photo Party é o operador (processador, nos termos do art. 28 do GDPR). Ao aceitar estes termos, as partes acordam que:',
                ],
                'items' => [
                    'Instruções: o Photo Party trata esses dados somente para prestar o serviço descrito (receber, armazenar, moderar, exibir no telão, disponibilizar galeria e exportação) e conforme as instruções documentadas do organizador, que são as configurações que ele faz no painel.',
                    'Base legal e menores: o organizador garante ter base legal para o tratamento, mantém o aviso aos convidados e declara, ao criar cada evento, ter autorização para coletar imagens dos participantes, inclusive dos responsáveis por menores.',
                    'Sigilo: as pessoas autorizadas a acessar esses dados estão sujeitas a dever de confidencialidade. O acesso da administração a fotos é excepcional (suporte ou exigência legal) e registrado em auditoria.',
                    'Segurança: medidas técnicas e organizacionais adequadas, descritas na política de privacidade (isolamento entre organizadores, criptografia em trânsito, controle de acesso, auditoria, remoção de metadados).',
                    'Suboperadores: o organizador autoriza a Hostinger como provedora de hospedagem (servidores em :region). Mudanças de suboperador serão informadas com antecedência razoável, e o organizador poderá se opor encerrando a conta.',
                    'Transferência internacional: conforme a seção 6 da política de privacidade.',
                    'Assistência: o Photo Party ajuda o organizador a responder pedidos de titulares (ferramentas "Minhas fotos", exclusão de fotos, exportação e encaminhamento das solicitações recebidas pelo formulário) e a cumprir obrigações de segurança e de comunicação de incidentes.',
                    'Incidentes: o Photo Party avisará o organizador sem demora injustificada após tomar conhecimento de incidente que afete os dados dos seus eventos, com as informações disponíveis.',
                    'Eliminação ao fim: ao fim do prazo de retenção do plano ou com a exclusão da conta, os arquivos e dados dos eventos são apagados, salvo obrigação legal de guarda.',
                    'Demonstração de conformidade: o Photo Party disponibiliza as informações necessárias para demonstrar o cumprimento destas obrigações e colabora com auditorias razoáveis, previamente combinadas.',
                ],
            ],
            [
                'id' => 'propriedade',
                'heading' => '7. Propriedade das fotos',
                'paragraphs' => [
                    'As fotos pertencem a quem as produziu e/ou às pessoas retratadas, nos termos da lei. O Photo Party não adquire direitos sobre elas e as usa apenas para prestar o serviço.',
                ],
            ],
            [
                'id' => 'responsabilidade',
                'heading' => '8. Disponibilidade e responsabilidade',
                'paragraphs' => [
                    'Buscamos manter o serviço disponível, mas ele pode sofrer interrupções. Recomendamos que o organizador baixe as fotos que quiser guardar antes do fim do prazo de retenção. Na medida permitida pela lei, não respondemos por danos indiretos; nada nestes termos limita direitos do consumidor que não possam ser limitados.',
                ],
            ],
            [
                'id' => 'alteracoes',
                'heading' => '9. Alterações e lei aplicável',
                'paragraphs' => [
                    'Estes termos têm versão e data e podem ser atualizados; mudanças relevantes serão comunicadas no painel. Contato: :contact. Lei aplicável e foro: [a definir com o advogado conforme o mercado (Brasil e/ou União Europeia)].',
                ],
            ],
        ],
    ],
];
