# Photo Party — Especificação do MVP

Versão de 16/11/2026. Fonte viva (com diagramas, concorrentes e mercado): documento "Photo Wall para Eventos — Concorrentes e Arquitetura do MVP" — https://claude.ai/artifact/9bBjbALVwuzJozCzHs6EXi

## 1. Visão geral

O convidado escaneia o QR code do evento, tira ou escolhe uma foto no celular, aplica moldura e filtro e envia. As fotos aprovadas aparecem num telão (TV ou projetor) em carrossel. O organizador cria eventos, modera, baixa tudo em ZIP. O administrador gerencia contas, planos e a página inicial.

## 2. Papéis e termos

| Termo | Quem é | No código |
| --- | --- | --- |
| Administrador | Dono da plataforma | `User` com `role = admin`, `account_id` nulo |
| Organizador (conta) | Fotógrafo, empresa de cabine, agência, empresa | `Account` (tenant) |
| Membro da equipe | Pessoa que entra no painel da conta | `User` com `role = owner` ou `moderator` |
| Convidado | Quem escaneia o QR e envia foto | Sem conta; identificador anônimo do aparelho (cookie + hash) |
| Telão | Tela do evento | Acessa por token do evento, sem login |

## 3. Fluxo principal

1. Organizador se cadastra e confirma o e-mail → conta em `trial`.
2. Cria evento, escolhe molduras, publica, baixa o QR code.
3. Convidado escaneia → servidor verifica se o evento está ao vivo. Se não: mensagem "Este evento já terminou" (ou "começa em...").
4. Convidado abre câmera (ou galeria), escolhe moldura/filtro, ajusta enquadramento; o navegador compõe, comprime e gera miniatura; envia.
5. Servidor valida limites, reencoda com GD e grava. Moderação prévia ligada → `pending`; desligada → `approved`.
6. Telão busca fotos aprovadas novas a cada 5 s e exibe.
7. Evento encerra no horário de fim. Plano pago → fotos em alta e ZIP liberados. Teste encerrado → só miniaturas.
8. Após o prazo de retenção, o cron apaga os arquivos.

## 4. Arquitetura (só Hostinger)

| Componente | Decisão |
| --- | --- |
| Domínio | Subdomínio `photo.uailabs.net`, document root em `/public`, SSL grátis do Hostinger (câmera exige HTTPS) |
| Backend | Laravel 11 + MySQL, monolito |
| Frontend | Blade + Alpine.js, Vite com build local; JS, CSS e fontes servidos pelo próprio domínio (sem CDN) |
| Câmera | `getUserMedia`; fallback `<input type="file" accept="image/*" capture="environment">` |
| Moldura e filtro | Canvas 2D no navegador; filtros via `ctx.filter` |
| Compressão | Navegador gera foto final (lado maior ~2000 px, JPEG 0,8, ~400 KB) e miniatura (~400 px, ~40 KB) |
| Upload | `multipart/form-data` direto ao Laravel, com retry no front |
| Storage | `storage/app/tenants/{account_uuid}/events/{event_uuid}/{original|thumb}/{photo_uuid}.jpg` — nunca em `public/` |
| Entrega de imagem | Rota `/media/{photo}/{variant}` com policy (painel) ou URL assinada com expiração de 10 min (telão). Headers `Cache-Control: private, max-age=600`, `X-Content-Type-Options: nosniff` |
| Tempo real | Polling: `GET /screen/{token}/photos?after={cursor}` a cada 5 s, JSON pequeno |
| Fila | Driver `database`; cron do hPanel a cada minuto: `php artisan schedule:run`; scheduler chama `queue:work --stop-when-empty --max-time=50` |
| ZIP | Job monta com `ZipArchive` sem recompressão (`CM_STORE`), salva na pasta do tenant, link no painel |
| QR code | `chillerlan/php-qrcode` (PNG e SVG) |
| E-mail | SMTP do Hostinger |
| Pagamento | Fora do MVP; admin ativa o plano manualmente |
| Deploy | Git + Composer via SSH; `public/build` gerado localmente |

## 5. Modelo de dados

Todas as tabelas de tenant têm `account_id` e usam a trait `BelongsToAccount` (global scope). Chaves públicas são UUID v4.

| Tabela | Campos principais | Observação |
| --- | --- | --- |
| `plans` | id, name, price, max_events_per_month, max_photos_per_event, retention_days, allows_custom_frames, white_label, is_public | Fonte única de preço; a home lê daqui |
| `accounts` | id, uuid, name, status (`trial`, `trial_ended`, `active`, `past_due`, `cancelled`), plan_id, trial_used_at, trial_ends_at, branding (JSON: logo, cores) | Tenant |
| `users` | id, account_id (nulo para admin), name, email, password, role (`admin`, `owner`, `moderator`), email_verified_at, two_factor_* | |
| `events` | id, uuid, account_id, name, slug, starts_at, ends_at, status (`draft`, `scheduled`, `live`, `ended`, `expired`), moderation (`pre`, `post`), is_trial, welcome_message, ask_guest_name, max_photos_per_guest, enabled_filters (JSON), upload_token_hash, screen_token_hash, screen_settings (JSON), language, expires_at | Status calculado pelos horários + ação do organizador |
| `frames` | id, uuid, account_id (nulo = modelo do sistema), type (`template`, `custom`), template_key, name, config (JSON), portrait_path, landscape_path | |
| `event_frame` | event_id, frame_id, sort_order | Molduras ativas no evento |
| `photos` | id, uuid, account_id, event_id, frame_id, filter, orientation, status (`pending`, `approved`, `rejected`), guest_name, guest_texts (JSON), device_hash, original_path, thumb_path, bytes, approved_at, created_at | Índice (event_id, status, approved_at) |
| `exports` | id, uuid, account_id, event_id, status, path, bytes, expires_at | |
| `home_sections` | id, key, enabled, sort_order, content (JSON por idioma), updated_by | Página inicial |
| `home_items` | id, section_id, enabled, sort_order, data (JSON por idioma) | Itens repetíveis |
| `site_settings` | key, value | SEO, contato, idioma padrão |
| `audit_logs` | id, user_id, account_id, action, subject_type, subject_id, meta (JSON), ip, created_at | OWASP A09 |
| `jobs`, `failed_jobs` | padrão Laravel | Fila `database` |

## 6. Ciclo de vida do evento

| Estado | Comportamento | Transição |
| --- | --- | --- |
| `draft` | Configurável; QR recusa envios | Organizador publica |
| `scheduled` | QR mostra "começa em..." | `now >= starts_at` |
| `live` | Envios, moderação e telão ativos | `now >= ends_at` ou organizador encerra |
| `ended` | Upload recusado (HTTP 410 + mensagem "Este evento já terminou"); galeria e ZIP conforme plano | Passa `retention_days` |
| `expired` | Cron apaga arquivos; registro mantido | — |

## 7. Teste grátis

| Regra | Valor |
| --- | --- |
| Período de teste da conta | 3 horas a partir da publicação do evento de teste |
| Duração máxima do evento de teste | 2 horas (`ends_at <= starts_at + 2h`, validado no servidor) |
| Limite de fotos | 20 por evento de teste |
| Envio após o fim | Bloqueado no servidor: HTTP 410, página mostra "Este evento já terminou" |
| Um teste por pessoa | Um por e-mail verificado; bloquear domínios de e-mail temporário (lista local) |
| Anti-robô | Confirmação de e-mail, honeypot, rate limit de cadastro por IP; sem captcha externo |
| Marca d'água | Selo discreto "Photo Party" no telão durante o teste |
| Após o teste | Organizador vê só miniaturas; alta resolução e ZIP só com plano pago |
| Sem assinatura | Fotos apagadas 7 dias depois; e-mail de aviso no 5º dia |

Estados da conta: `trial` → `trial_ended` → `active` → `past_due` / `cancelled`. Middleware bloqueia criar/publicar eventos fora de `trial` (com teste disponível) e `active`.

## 8. Área do organizador (telas do MVP)

| Tela | Conteúdo |
| --- | --- |
| Login | E-mail e senha, recuperar senha (2FA depois) |
| Cadastro | Nome, e-mail, senha, aceite de termos; honeypot |
| Painel | Eventos ao vivo, próximos, encerrados; uso do plano |
| Criar/editar evento | Nome, início/fim, moderação pré/pós, boas-vindas, pedir nome do convidado, limite por convidado, filtros, molduras |
| QR code | Baixar PNG e SVG |
| Moderação ao vivo | Grade de pendentes com polling 5 s; aprovar/rejeitar em lote; remover aprovada do telão |
| Telão | Abrir telão, tempo por foto, transição, QR no canto, gerar novo token |
| Galeria | Fotos por status; download individual (conforme plano) |
| Exportar | Pedir ZIP, ver status, baixar |
| Molduras | Modelos prontos + envio de moldura própria, com prévia |
| Minha conta | Dados, plano e uso, excluir conta e todos os dados (obrigatório) |

Depois do MVP: equipe (convidar membros), 2FA, placa de mesa em PDF, white-label com domínio próprio.

## 9. Página do convidado (`/e/{slug}?t={upload_token}`)

1. Checa estado do evento no servidor; mostra boas-vindas ou aviso.
2. Aviso de consentimento (GDPR/LGPD) antes da primeira foto.
3. Câmera ou galeria → escolha de moldura (só as ativas no evento, mais "sem moldura") → filtro → arrastar/zoom → textos do convidado se o modelo pedir.
4. Composição no Canvas, compressão, miniatura, envio com retry e barra de progresso.
5. Confirmação: "Foto enviada!" (+ "aguardando aprovação" se moderação prévia).
6. Nunca lista fotos de outros convidados. Convidado pode apagar a própria foto enquanto pendente (via `device_hash`).

## 10. Telão (`/screen/{token}`)

- Tela cheia, carrossel; tempo por foto e transição configuráveis.
- Polling a cada 5 s com cursor; mantém fila local das fotos já baixadas e continua girando se a rede cair.
- Mostra miniaturas grandes (ou versão média) por URL assinada.
- QR code do evento no canto (opcional). Selo no modo teste.
- Token revogável pelo organizador (novo token derruba o antigo).

## 11. Molduras

| Orientação | Tamanho final | Proporção |
| --- | --- | --- |
| Retrato | 1080 × 1350 px | 4:5 |
| Paisagem | 1920 × 1080 px | 16:9 |

Modelos prontos:

| `template_key` | Visual | Textos | Efeito |
| --- | --- | --- | --- |
| `led_billboard` | Painel de LED gigante numa fachada urbana à noite (fundo genérico, sem marcas reais) | Nome do evento | Pontos de LED + brilho |
| `magazine_cover` | Capa de revista inventada (nome e logo próprios) | Título (organizador), 2–3 manchetes, data | — |
| `wanted_poster` | Cartaz de procurado, papel envelhecido | "Procurado"/"Wanted" pelo idioma, nome (convidado), recompensa (organizador) | Sépia automático |
| `floral` | Flores nos cantos | Nome do evento e data | — |

`config` (JSON) por orientação: `background` (imagem abaixo da foto), `window` (x, y, w, h), `overlay` (PNG acima), `texts[]` (id, x, y, maxWidth, font, size, color, maxChars, filledBy: `organizer`|`guest`, default), `effect` (`led`, `sepia`, null). Fontes locais com licença OFL.

Moldura do organizador: PNG transparente, uma versão por orientação nos tamanhos acima, até 2 MB; o sistema detecta a área transparente como janela; validação de tipo/dimensão e reencode com GD; gabarito para download.

## 12. Admin

- Contas: listar, ver uso, mudar plano, ativar/suspender, estender teste.
- Planos: CRUD.
- Molduras do sistema: CRUD dos modelos prontos.
- Página inicial: seções e itens com toggle, ordem e conteúdo por idioma; botão "Visualizar"; cache de arquivo limpo ao salvar.
- Auditoria: tudo registrado em `audit_logs`, incluindo acesso do admin a fotos.

### Página inicial (seções em `home_sections`)

`hero` (com demonstração ao vivo: QR + mini-telão de um evento de demonstração que se limpa a cada hora), `how_it_works`, `audiences`, `features`, `pricing` (lê `plans` com `is_public`), `faq`, `final_cta`. Rodapé com termos e privacidade não pode ser desligado. Formulário por tipo de seção (schema), sem HTML livre.

## 13. Isolamento entre organizadores

1. Disco por tenant e evento, fora do document root; caminho montado só pelo servidor.
2. `account_id` em toda tabela de tenant + global scope + `scopeBindings()`.
3. Policy em toda rota; recurso de outro tenant → 404.
4. Telão: token por evento (salvo como hash), só fotos aprovadas daquele evento, URLs assinadas com expiração.
5. Convidado: só envia; `account_id`/`event_id` derivados do token do QR.
6. ZIP na pasta do tenant, rota autorizada, apagado após alguns dias.
7. UUID é tratado como público: segurança vem da autorização, não do sigilo do ID.
8. Rate limit e log de rajadas de 404 em `/media`.
9. Testes automatizados de isolamento obrigatórios (conta A × conta B) para foto, evento, moldura, ZIP, moderação e telão.

## 14. Segurança — OWASP Top 10:2025

| Risco | Implementação |
| --- | --- |
| A01 Broken Access Control | Seção 13; CSRF padrão; URLs assinadas |
| A02 Security Misconfiguration | `APP_DEBUG=false`, `APP_ENV=production`, root em `/public`, `.env`/`.git` inacessíveis, sem listagem de diretório; middleware de headers: CSP restrita a `self`, HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self)` |
| A03 Software Supply Chain Failures | Poucas dependências; lockfiles versionados; `composer audit` e `npm audit` antes do deploy; nada de CDN |
| A04 Cryptographic Failures | HTTPS + HSTS; bcrypt/argon2; tokens `Str::random(64)` salvos como hash; `APP_KEY` fora do Git |
| A05 Injection | Eloquent/bindings; Blade `{{ }}`; upload validado (`finfo`, `getimagesize`, tamanho, dimensões) e reencodado com GD (remove EXIF/GPS e conteúdo embutido); nome de arquivo sempre UUID |
| A06 Insecure Design | Rate limit por aparelho/IP; janela de upload; limites por plano; moderação pré padrão no corporativo |
| A07 Authentication Failures | Throttle no login; senha forte; sessão `secure`/`httponly`/`samesite=lax`; regenerar sessão no login; 2FA (pós-MVP) |
| A08 Software or Data Integrity Failures | Deploy por tag Git; nada de `unserialize` em dado do usuário; IDs de tenant/evento nunca do cliente |
| A09 Security Logging and Alerting Failures | `audit_logs`; logs de login/falhas/moderação/404 em `/media`; alerta por e-mail via cron; nunca logar tokens ou senhas |
| A10 Mishandling of Exceptional Conditions | Páginas de erro genéricas; autorização nega em caso de erro; foto + registro em transação; disco cheio → recusa clara; cron limpa órfãos |

Antes de cada versão: `php artisan test` (incluindo isolamento) + OWASP ZAP baseline scan contra o ambiente Docker local.

## 15. Limites do Hostinger compartilhado

- Processos simultâneos limitados: manter requisições curtas (fotos pequenas, polling leve, cache).
- Disco/inodes: ~450 KB por foto (original + miniatura); cota por plano e expiração automática.
- Sem processos longos: tudo assíncrono passa pelo cron.
- Se o pico não aguentar: migrar para VPS do Hostinger sem mudar o código.

## 16. Roadmap (um módulo por sessão do Claude Code)

1. **Base:** projeto Laravel, Docker local, auth, cadastro com confirmação de e-mail, `Account` + trait `BelongsToAccount`, planos, estados da conta, headers de segurança, testes de isolamento base.
2. **Eventos:** CRUD, estados, tokens (upload e telão), QR code, regras do teste (2 h, 20 fotos, 3 h de conta).
3. **Página do convidado:** câmera/fallback, molduras (4 modelos + custom), filtros, ajuste, compressão, miniatura, upload com retry, consentimento.
4. **Upload no servidor:** validação, reencode GD, gravação por tenant, limites, HTTP 410 após o fim.
5. **Telão:** carrossel, polling, URLs assinadas, fila local, selo de teste.
6. **Moderação e galeria:** fila de pendentes com polling, lote, remover do telão, galeria por status.
7. **Exportação:** job de ZIP pelo cron, download autorizado, expiração.
8. **Molduras:** biblioteca da conta, upload de moldura própria com detecção de janela e prévia.
9. **Admin:** contas, planos, molduras do sistema, auditoria.
10. **Página inicial administrável:** seções, itens, toggles, ordem, idiomas, prévia, cache.
11. **Limpeza e operação:** expiração, avisos por e-mail, limpeza de órfãos, alertas.
12. **Segurança e deploy:** revisão OWASP, ZAP, deploy no Hostinger, teste de carga (100–200 uploads), evento piloto.

## 17. Decisões em aberto

- Gateway de pagamento: depois de validar o sistema.
- Prazo de retenção por plano pago.
- Idioma/mercado inicial (Brasil, Irlanda/Europa ou ambos).
