# Photo Party

Plataforma de fotos para eventos: o convidado escaneia um QR code, tira uma foto no navegador, aplica moldura/filtro e envia; as fotos aprovadas aparecem num telão. Especificação completa: `SPEC.md` (pasta de contratos do projeto).

Este repositório contém os módulos **1 (Base)** e **2 (Eventos)** do roadmap (SPEC, seção 16).

## Stack e versões

Laravel 13 (`laravel/framework ^13.17`), PHP 8.3+, Pest 4 (PHPUnit 12), `chillerlan/php-qrcode` 6, Vite 8 + `laravel-vite-plugin` 3, Tailwind 3 (via PostCSS), Alpine (build CSP), Vitest 5.

## Requisitos

- PHP 8.3 ou superior com as extensões `gd`, `mbstring`, `pdo_mysql` (e `pdo_sqlite` para os testes)
- Composer 2
- Node 20.19+ ou 22.12+ (exigência do Vite 8; só na máquina local, para gerar `public/build`)
- MySQL/MariaDB (produção e ambiente Docker local)

## Como rodar localmente

Um comando por linha (funciona igual no Bash e no PowerShell):

```bash
composer install
cp .env.example .env
php artisan key:generate
# ajuste DB_* no .env (ambiente Docker hostinger-dev ou MySQL local)
php artisan migrate --seed
npm install
npm run build
php artisan test
npm run test:js
```

No PowerShell, `cp` funciona como alias de `Copy-Item` (ou use `copy .env.example .env` no cmd). Para encadear comandos numa linha, o PowerShell 5 (Windows PowerShell) usa `;` em vez de `&&`, por exemplo: `composer install; php artisan test`. O `&&` só existe no PowerShell 7+.

Atalho: `composer setup` instala dependências, cria o `.env`, gera a chave, migra com seed e faz o build.

O `.npmrc` usa `ignore-scripts=true` (padrão do skeleton do Laravel 13): scripts de instalação de pacotes npm não rodam.

Para desenvolvimento com recarga automática: `npm run dev` em um terminal e o site pelo vhost `.test` do Docker (ou `php artisan serve`). Em `APP_ENV=local`, a CSP libera automaticamente o servidor do Vite enquanto o arquivo `public/hot` existir.

Antes de cada deploy: `composer audit` e `npm audit`.

### Usuários de demonstração (seed)

Senha de todos: valor de `SEED_PASSWORD` no `.env` (padrão `Password!123`). O seeder de demonstração se recusa a rodar com `APP_ENV=production`; em produção rode só `php artisan db:seed --class=PlanSeeder`.

| E-mail | Papel | Conta |
| --- | --- | --- |
| `admin@photoparty.test` | admin | nenhuma |
| `owner-a@demo.test` | owner | A: ativa, plano Profissional, 1 evento agendado + 1 rascunho |
| `moderator-a@demo.test` | moderator | A |
| `owner-b@demo.test` | owner | B: em teste, 1 rascunho de evento de teste |

## Admin e página inicial (módulo 9/10)

Admin em `/admin` (middleware `auth`, `admin`, `verified`; qualquer usuário que não seja admin recebe 404). Rotas em `routes/admin.php`; a página pública em `routes/home.php`.

- **Contas:** busca e filtro por status, uso (eventos, fotos, espaço), mudar plano, ativar/suspender, estender teste (+N horas).
- **Planos:** CRUD completo, incluindo `is_public` (a home lê apenas os públicos).
- **Molduras do sistema:** ativar/desativar e ordenar (upload de novos modelos fica para depois).
- **Auditoria:** `audit_logs` paginado com filtros; toda ação do admin passa por `App\Services\AuditLogger`.
- **Página inicial (`/admin/home`):** seções com toggle e ordem, conteúdo por idioma (pt_BR/en) com campos definidos em `config/home_sections.php`, itens repetíveis, botão Visualizar (sem salvar), SEO e contato. O conteúdo é texto puro, sempre escapado.
- **Home pública (`/`):** só seções ativas, em cache de arquivo por idioma (limpo ao salvar no admin). Rodapé com Termos (`/termos`) e Privacidade (`/privacidade`) sempre presente; texto-modelo LGPD/GDPR completo (ver "Privacidade (LGPD/GDPR)"), ainda pendente de revisão jurídica.
- **Conteúdo inicial:** `php artisan db:seed --class=HomeSeeder` (idempotente, seguro em produção; não sobrescreve edições).
- **Demonstração ao vivo** do hero: por enquanto um bloco estático (`#live-demo`).

Credenciais de demonstração do seeder (apenas local): `admin@photoparty.test` com a senha de `SEED_PASSWORD` (padrão `Password!123`); acesse `/login` e depois `/admin`.

## Deploy no Hostinger

- **Versão do PHP por site:** no hPanel, defina **PHP 8.3** para o subdomínio `photo.uailabs.net`. A versão é configurada por site; o `uailabs.net` continua em 8.2.
- **Linha de comando (SSH):** o `php` padrão do terminal pode ser outra versão. Rode Composer e Artisan com o binário do 8.3:

  ```bash
  /opt/alt/php83/usr/bin/php $(which composer) install --no-dev --optimize-autoloader
  /opt/alt/php83/usr/bin/php artisan migrate --force
  /opt/alt/php83/usr/bin/php artisan config:cache
  /opt/alt/php83/usr/bin/php artisan route:cache
  /opt/alt/php83/usr/bin/php artisan view:cache
  ```

- **`composer.lock`:** o `composer.json` fixa `config.platform.php = 8.3.0`, então o lock gerado no Windows (mesmo com PHP mais novo) só resolve pacotes compatíveis com o PHP 8.3 do servidor. Versione o `composer.lock`.
- **Extensões** já habilitadas no servidor: `gd`, `zip`, `fileinfo`, `intl`, `mbstring`, `pdo_mysql` (`nd_pdo_mysql`), `exif`, `imagick`.
- **Assets:** rode `npm run build` localmente e envie `public/build` (não há Node no servidor).
- **Produção:** `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, document root em `/public`.

### Cron (Hostinger)

No hPanel, um cron a cada minuto (com o binário do PHP 8.3):

```
* * * * * cd /caminho/do/projeto && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

O agendamento (`routes/console.php`) roda: `accounts:expire-trials`, `events:refresh-statuses` (a cada minuto), `exports:purge-expired` (de hora em hora), `privacy:enforce-retention` (diário, 03:17) e, por último, `queue:work --stop-when-empty --max-time=50 --tries=3`.

## O que está implementado

**Base (módulo 1)**

- Models `Plan`, `Account` (tenant), `User` (papéis `admin`, `owner`, `moderator`), `Event`, `Frame`, `Photo`, `Export`, `AuditLog`.
- Trait `App\Models\Concerns\BelongsToAccount`: global scope pela conta do usuário autenticado e preenchimento automático de `account_id` na criação.
- Migrations de `plans`, `accounts`, `users` (ajustada), `events`, `frames`, `event_frame`, `photos`, `exports`, `audit_logs`; chaves públicas UUID v4.
- Autenticação sem Breeze/Jetstream (Blade + Alpine): cadastro (nome, e-mail, senha forte, aceite de termos, honeypot, rate limit por IP, bloqueio de e-mail temporário), confirmação de e-mail obrigatória, login com throttle, logout, recuperação de senha.
- Estados da conta (`trial`, `trial_ended`, `active`, `past_due`, `cancelled`) e middleware `account.can-manage-events`.
- Middleware `SecurityHeaders` (CSP `self`, HSTS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy: camera=(self), geolocation=(self)`).
- Interface em pt_BR e en (`lang/`), troca de idioma em `/locale/{pt_BR|en}`.

**Eventos (módulo 2)**

- CRUD do organizador, ações publicar, encerrar, gerar novo link do telão, baixar QR (PNG e SVG).
- Estados `draft`, `scheduled`, `live`, `ended`, `expired` calculados pelos horários (`Event::computeStatus`) e mantidos em dia pelo comando `events:refresh-statuses`.
- Regras do teste grátis no servidor (`App\Services\EventScheduler`).
- `EventPolicy` + `scopeBindings()`; recurso de outra conta responde 404.

**Convidado e mídia (módulos 3-4)** — rotas em `routes/guest.php`

- `FrameSeeder` registra os 4 modelos de `public/frames/<key>/config.json` como molduras do sistema (`account_id` nulo, `type = template`); idempotente e chamado pelo `DatabaseSeeder` (em produção: `php artisan db:seed --class=FrameSeeder --force`). Todo evento novo ativa esses modelos (`Event::attachDefaultFrames()`, hook `created`).
- `GET /e/{slug}?t=…` (`guest.show`): evento por slug + hash do token (token errado, de outro evento ou ausente → 404). `draft`/`expired` → 404; `scheduled` → página "começa em…"; `ended` → "Este evento já terminou" (HTTP 410); `live` → app do convidado (`guest/show.blade.php`) no idioma do evento. Cria o cookie anônimo do aparelho `pp_device` (aleatório, criptografado, httponly, secure, samesite=lax, 30 dias); no banco fica só o HMAC (`photos.device_hash`).
- `POST /e/{slug}/photos?t=…` (`guest.photos.store`): 201 `{uuid, status}`. Servidor decide tudo: evento fora do ar → 410; limite de fotos do evento (20 no teste, plano nos demais) ou `max_photos_per_guest` por aparelho → 429; rate limit `guest-upload` (6/min por aparelho, 60/min por IP) → 429; validação (`finfo` + `getimagesize`, JPEG/PNG/WebP, foto ≤ 3 MB e 1080×1350 ou 1920×1080 conforme a orientação, miniatura ≤ 300 KB e ≤ 400 px no lado maior, `frame_key` vazio ou ativo no evento, textos do convidado só nos campos `filledBy: guest` da moldura/orientação, ≤ `maxChars`, sem HTML) → 422. Foto e miniatura são reencodadas com GD para JPEG (sem EXIF/GPS nem conteúdo embutido) e gravadas em `storage/app/private/tenants/{account_uuid}/events/{event_uuid}/{original|thumb}/{photo_uuid}.jpg` dentro da transação do registro; em falha os arquivos são apagados. Disco cheio/erro de escrita → 507. Status `pending` (moderação prévia) ou `approved` + `approved_at` (posterior).
- `DELETE /e/{slug}/photos/{photo}?t=…` (`guest.photos.destroy`): o convidado apaga a própria foto em qualquer status enquanto o evento está ao vivo (confere `device_hash`); de outro aparelho → 404. Ver "Privacidade (LGPD/GDPR)".
- `GET /media/{photo}/{variant}` (`media.show`, `variant` = `thumb|original`): login + `PhotoPolicy` (mesma conta, owner ou moderator; admin permitido e auditado em `audit_logs`; outra conta → 404). `GET /media/signed/{photo}/{variant}` (`media.signed`): URL assinada **relativa** de 10 min para o telão, só fotos aprovadas de evento não expirado — gere com `Photo::signedMediaUrl('thumb')`; no painel use `Photo::mediaUrl('thumb')`. Conta `trial_ended` só recebe `thumb` (original → 404). Headers `Content-Type: image/jpeg`, `Cache-Control: private, max-age=600`, `X-Content-Type-Options: nosniff`.
- Rajadas de 404/403 em `/media`: middleware `GuardMediaNotFound` (20 por minuto por IP → log no canal `security`, `storage/logs/security-*.log`, e 429 até a janela acabar) + throttle nomeado `media`.

**Telão e moderação (módulos 5-6)** — rotas em `routes/screen.php` e `routes/photos.php`

- `GET /screen/{token}` (`screen.show`): sem login; evento por hash do token. Token inválido/revogado, `draft` ou `expired` → 404; `scheduled` → "Começa em…" (recarrega a cada 30 s); `ended` → continua mostrando as aprovadas com aviso discreto e sem QR. Resposta `no-store` e `Referrer-Policy: no-referrer` (o token está na URL). Os dados vão num bloco `<script type="application/json" id="pp-screen">`; nenhum script/estilo inline. O QR do convidado é gerado no servidor (`QrCodeGenerator`, SVG) e embutido como `data:` URI. Selo "Photo Party" quando o evento é de teste ou a conta está em `trial`.
- `GET /screen/{token}/photos?after={cursor}` (`screen.photos`, throttle `screen` 120/min por IP): JSON `{status, photos[{uuid,url,orientation}], removed[], cursor, has_more, ttl}`. Só fotos aprovadas do evento do token, no máximo 50 por chamada, URL assinada de 10 min da variante `original` (`thumb` se a conta estiver em `trial_ended`). Cursor opaco `{approved_at em µs}_{uuid}` (ordem `approved_at, id`); `removed` traz as rejeitadas/retiradas desde o cursor (até 200). Cursor inválido → 422.
- Front em `resources/js/screen/`: `queue.js` (fila local com prioridade às novas, remoção e controle de validade das URLs), `sync.js` (polling de 5 s com cursor e "refazer a busca" para renovar URLs antes de expirar), `image-cache.js` (até 40 imagens em memória: o telão continua girando sem rede), `index.js` (DOM, transições, tela cheia). Tempo por foto, transição (`fade|slide|zoom|none`) e QR vêm de `events.screen_settings` (padrões: 6 s, fade, QR ligado). Se o servidor responder 404, o telão mostra "link não está mais ativo" e para.
- Moderação (`auth` + `verified` + `account`, `scopeBindings`): `GET /events/{event}/moderation` (abas Pendentes/Aprovadas/Rejeitadas, seleção múltipla, lote e ação individual, atualização a cada 5 s), `GET …/moderation/photos?status=` (JSON do polling, até 200 por aba), `POST …/moderation/batch` (`action=approve|reject`, até 200 uuids; uuid de outro evento/conta é ignorado e contado em `ignored`) e `POST …/moderation/photos/{photo}/{approve|reject}`. "Tirar do telão" = aprovada → rejeitada; "Voltar ao telão" = rejeitada → aprovada (ganha novo `approved_at`, então reaparece no telão). Evento com moderação posterior abre na aba Aprovadas. Cada mudança grava `audit_logs` (`photo.approved`, `photo.rejected`, `photo.removed_from_screen`, `photo.restored_to_screen`).
- `EventPolicy::moderate`: owner **e** moderator da conta (outra conta → 404; evento expirado → 403). O botão "Abrir telão" (token da cópia criptografada, nova aba) na página do evento aparece só para quem pode gerenciar tokens (owner).
- Migration `2026_11_16_120000`: `photos.approved_at` passa a ter microssegundos (e `Photo::$dateFormat` grava com `.u`), para o cursor não perder fotos aprovadas no mesmo segundo.

## Galeria e download

Regra de plano (decisão do dono do produto; substitui a do SPEC), centralizada em `Account::canDownloadPhotos()` (= status `active`) e `App\Services\Photos\PhotoVariants`:

- **Conta `active`:** miniaturas normais (~400 px), download de cada foto em tamanho original e ZIP do evento. Sem marca d'água.
- **Conta em teste (`trial`, `trial_ended`) ou `past_due`/`cancelled`:** só prévia de baixíssima resolução (lado maior 200 px, JPEG ~60), sem nenhum download. Vale para galeria e moderação.
- `media.show`: conta não-active pedindo `thumb` recebe a prévia (gerada com GD a partir do thumb e cacheada em `.../preview-v2/{uuid}.jpg`, já com faixa diagonal "DEMONSTRAÇÃO"/"DEMO" conforme o idioma do evento); pedindo `original` recebe 404.
- O telão (`media.signed`) não muda as imagens; para conta não-active ele exibe uma faixa diagonal CSS "DEMONSTRAÇÃO · Conta de teste" no lugar do selo. Galeria e moderação mostram o rótulo "DEMONSTRAÇÃO" sobre cada miniatura.
- Galeria: `GET /events/{event}/gallery` (aprovadas, paginada, `routes/photos.php`). Download: `/events/{event}/photos/{photo}/download` (`evento-slug-0001.jpg`). Conta não-active vê o aviso de prévia e recebe 404 nos downloads.
- ZIP: o botão cria um registro em `exports` e despacha `BuildEventExport` (fila `database`, cron). Um ZIP em andamento por evento; arquivo em `tenants/{account}/events/{event}/exports/{uuid}.zip` (CM_STORE). A tela mostra o status e um link "Atualizar" (sem JS). O ZIP expira em 7 dias; `exports:purge-expired` (agendado de hora em hora) apaga o arquivo.

## Molduras personalizadas

Módulo 8. Rotas em `routes/frames.php`; código em `App\Http\Controllers\Frames\FrameLibraryController`, `App\Http\Controllers\Guest\GuestFrameController`, `App\Services\Frames\*` e `App\Policies\FramePolicy`; telas em `resources/views/frames/`; textos em `lang/*/frames.php`.

- **Regra de plano:** `Account::canUseCustomFrames()` = status `active` **e** plano com `allows_custom_frames`. Sem isso a tela **Molduras** (`/frame-library`, link no menu e na página do evento) mostra os 4 modelos prontos e o aviso "Molduras personalizadas com a sua marca estão disponíveis nos planos pagos.", sem formulário; `POST` direto → 403 com a mesma mensagem. Moldura de outra conta → 404. Moderador só visualiza.
- **Prefixo `/frame-library`** (e não `/frames`): `public/frames/` é a pasta dos modelos prontos e, no Apache/servidor embutido, um diretório real com o mesmo nome encobriria a rota.
- **Envio:** nome + retrato 1080×1350 e/ou paisagem 1920×1080 (pelo menos um), PNG até 2 MB cada. `CustomFrameImage` valida `finfo` = `image/png`, `getimagesize` com dimensões exatas, canal alfa no IHDR (tipo 4/6 ou paleta com `tRNS`); redesenha com GD numa imagem truecolor nova com alfa preservado e grava com `imagepng` (some com `tEXt`/`iTXt`/`eXIf` e bytes depois do `IEND`). A janela é a bounding box dos pixels com alfa ≥ 100/127, amostrada a cada 2 px (~0,1 s na paisagem); sem transparência ou janela < 30% da imagem → recusa. Limite de 20 por conta (contagem com `lockForUpdate` na conta). Throttle 20/min.
- **Armazenamento:** `storage/app/private/tenants/{account_uuid}/frames/{frame_uuid}/{portrait|landscape}.png` + `config.json` (também em `frames.config`), no formato de `docs/frame-config.md`: `background: null`, `overlay: "<orientação>.png"`, `window` = bbox, `texts: []`, `effect: null`, só as orientações enviadas. Nada em `public/`.
- **Convidado (decisão):** em vez de URL assinada, as molduras custom saem por `GET /e/{slug}/frames/{frame}/{config.json|portrait.png|landscape.png}?t={token de upload}` (`guest.frames.asset`), com a **mesma autorização do envio de fotos**: evento por slug + hash do token, evento `live`, e a moldura precisa estar em `Event::activeFrames()` daquele evento. É o mais simples (sem expiração para renovar durante a festa) e não abre nada além do que o QR já dá. O payload da página do convidado traz `configUrl` com o `?t=` e `orientations`; `resolveAssetUrl()` (JS) repassa a query do config para o PNG.
- **`Event::activeFrames()`** agora exclui moldura custom de outra conta e de conta que não pode usá-las (plano rebaixado → some da página e o upload com esse `frame_key` dá erro de validação).
- **Uma orientação só:** o payload informa `orientations`; no JS, `frameOptions` mostra só as molduras da orientação da foto e, ao virar a orientação, uma moldura que não existe nela é retirada (foto fica sem moldura).
- **Ativação por evento (decisão):** na própria tela de molduras, cada moldura custom tem a lista de eventos não encerrados da conta com caixas de seleção (`PUT /frame-library/{frame}/events`). Eventos de outra conta enviados no formulário são ignorados. A moldura custom entra depois dos modelos prontos (`sort_order` 100).
- **Prévia:** SVG com a "foto de exemplo" (gradiente) na `window` e o PNG por cima (`GET /frame-library/{frame}/{orientação}.png`, login + policy). Compatível com a CSP (sem estilo inline).
- **Gabarito:** `GET /frame-library/guide/{portrait|landscape}.png`, gerado com GD (`FrameGuide`): moldura cinza semitransparente, janela sugerida transparente com contorno vermelho e margem de segurança tracejada. O gabarito enviado sem alterações passa na validação.
- **Exclusão:** apaga a pasta da moldura e os vínculos com eventos; fotos já enviadas ficam com `frame_id` nulo.
- **Auditoria:** `frame.uploaded`, `frame.events_updated`, `frame.deleted`.
- **Admin:** a tela de molduras do admin continua só com os modelos do sistema (há teste garantindo que ela não mexe em moldura de organizador). Desativar uma moldura custom de qualquer conta pelo admin fica pendente; hoje dá para fazer `frames.is_active = false` direto no banco (o convidado deixa de vê-la e a tela do organizador mostra "Desativada pela administração").

## Geolocalização (recurso de plano)

"Envio só no local do evento": o convidado só envia fotos se o aparelho estiver dentro de um raio do local do evento.

- **Regra de plano:** `plans.allows_geofence` (admin de planos; aparece como diferencial na seção de planos da home). Centralizada em `Account::canUseGeofence()` (conta `active` **e** plano com o recurso). Sem o recurso, o formulário do evento mostra a opção desabilitada com "Disponível nos planos com geolocalização" e o servidor ignora os campos enviados.
- **Evento:** `events.geofence_enabled`, `geofence_lat`, `geofence_lng` (6 casas), `geofence_radius_m` (padrão 100, 50–2000, validado no servidor). O local é definido sem serviço de mapas: botão "Usar minha localização atual" (Geolocation API do organizador, mostra a precisão) ou campo para colar um link do Google Maps / "lat, lng", interpretado no servidor por `App\Services\Geofence\CoordinateParser` (`!3d…!4d…`, `?q=`, `query=`, `ll=`, `/@lat,lng,zoom`, `/place/lat,lng`, texto puro). Links curtos (`maps.app.goo.gl`, `goo.gl/maps`) não são resolvidos (exigiria chamada externa) e geram mensagem pedindo o link completo ou as coordenadas. O formulário e a página do evento mostram as coordenadas salvas e um link simples "ver no mapa".
- **Convidado:** com a geofence em vigor (`Event::geofenceActive()`: ligada, com local e conta ainda com o recurso) a página ganha o passo "Confirme que você está no local" entre o consentimento e a câmera (mesmo layout de tela cheia, sem rolagem). O JS (`resources/js/guest/location.js`) chama `getCurrentPosition({enableHighAccuracy:true, timeout:15000, maximumAge:0})` e envia `latitude/longitude/accuracy/timestamp` para `POST /e/{slug}/location?t=…` (`guest.location`, throttle `guest-location`: 10/min por aparelho, 120/min por IP). O servidor (`LocationCheck`, haversine) recusa precisão > 150 m (`inaccurate`) e leitura com mais de 2 min (`stale`); aceita se `distância − min(precisão, 150) <= raio`; senão `outside` com a distância arredondada. Aceito → "passe de local" na sessão por 30 min, vinculado ao `device_hash` e ao evento. Permissão negada → instruções para iPhone/Android e "Tentar de novo".
- **Upload:** com a geofence em vigor, `POST /e/{slug}/photos` sem passe válido (ausente, vencido, de outro evento ou de outro aparelho) → **403** `{code: "location_required"}`; o front volta ao passo de localização e, confirmado, retorna à prévia sem perder a foto. A foto grava só `photos.location_verified` (bool). Conta que perdeu o plano: o evento volta ao comportamento normal (a configuração fica guardada).
- **Privacidade (LGPD/GDPR):** coordenadas do convidado **nunca** são gravadas em banco, log ou sessão (há teste que varre as tabelas, o log e a sessão). O aviso de consentimento ganha uma frase sobre a localização só quando a geofence está ativa; a política de privacidade (pt_BR/en) tem a seção 15 "Geolocalização" (finalidade, não armazenamento, base legal consentimento). Versões: `consent_version` e `legal_version` = `2026-11-18.2`.
- **Segurança:** `Permissions-Policy: geolocation=(self)`. **Limitação:** as coordenadas vêm do aparelho e podem ser falsificadas por quem souber (extensões, modo desenvolvedor, apps de GPS falso). As mitigações — precisão máxima, leitura recente, passe curto e conferência no servidor — reduzem o abuso casual, não o eliminam.
- **Testes:** `tests/Feature/Geofence/*` (Pest) e `tests/js/location.test.js` (vitest). Capturas do passo do convidado (360×640 e 390×844: pedindo permissão, dentro do raio, fora do raio, imprecisa, negada) verificadas sem rolagem.

## Privacidade (LGPD/GDPR)

Papéis: o **organizador** (conta) é o **controlador** das fotos e dados dos convidados dos eventos dele; o Photo Party é o **operador/processador** desses dados e o **controlador** dos dados das contas. Base legal do convidado: consentimento (LGPD art. 7º I / GDPR art. 6(1)(a)). Imagem é tratada como dado pessoal comum (sem reconhecimento facial, biometria ou perfilamento).

**Convidado**

- **Consentimento registrado:** a página do convidado mostra um resumo (controlador = nome da conta do organizador, finalidade, retenção do plano, links "Ler política completa" e "Pedir acesso ou exclusão") e um checkbox obrigatório. O servidor recusa (422) envio sem `consent=1` e `consent_version` igual a `config('photoparty.privacy.consent_version')`; a foto grava `consent_version` e `consented_at`. Mudou o texto? Troque a versão: o aceite lembrado no aparelho (`localStorage`) usa a versão na chave, então todos aceitam de novo.
- **"Minhas fotos"** (acesso, portabilidade e eliminação), só enquanto o evento está `live` (decisão do produto): `GET /e/{slug}/my-photos?t=…` (lista JSON), `…/my-photos/{photo}/thumb`, `…/my-photos/{photo}/download` (original, `Content-Disposition: attachment`) e `DELETE /e/{slug}/photos/{photo}`. Autorização = token de upload do evento **e** `device_hash` do cookie; foto de outro aparelho/evento/conta → 404; fora de `live` → 404. Rate limit `guest-mine` (150/min por aparelho, 600/min por IP).
- **Regra de plano (decisão):** a foto é do próprio convidado, então o download dele é sempre o **original, sem faixa de demonstração**, mesmo com a conta do organizador em teste. A regra de prévia/sem download (`PhotoVariants`) continua valendo só para o painel do organizador.
- **Excluir = revogar o consentimento**, em qualquer status enquanto o evento está ativo: apaga original, miniatura e prévia. Foto pendente/rejeitada perde o registro; foto aprovada vira um registro mínimo `status = deleted` (sem arquivos, nome, textos nem moldura) só para o telão retirá-la (`removed` do polling); o registro some em 24 h. Foto excluída não ocupa vaga do evento, mas conta no limite por aparelho.
- **Depois do evento:** formulário público `/privacidade/solicitacao` (tipo, link do evento, e-mail, detalhes, declaração; honeypot `website`; 5/hora por IP; sem guardar IP). Cria `privacy_requests` (status aberto/concluído). O servidor tenta ligar ao evento pelo slug do link (para encaminhar ao organizador). E-mail: só um aviso **sem dados pessoais** ao contato de privacidade, e só se houver SMTP (`MAIL_MAILER` diferente de `log`/`array`) e contato preenchido; senão fica só no admin.
- **Cookie do aparelho `pp_device`:** 30 dias (antes 1 ano), httponly, secure (fora de local/testes), samesite=lax. Só cookies estritamente necessários (sessão, `XSRF-TOKEN`, `remember_web_*` se marcar "lembrar", `pp_device`) e nenhum script/fonte/CDN de terceiros (há teste que varre views/JS/CSS): por isso **sem banner de cookies**; a política tem a seção "Cookies" com nome, finalidade e duração.

**Organizador**

- Criar evento exige o checkbox "Declaro que tenho autorização para coletar imagens dos participantes, inclusive de menores, nos termos da LGPD/GDPR" (`events.declared_at`).
- **Minha conta** (`/account`, link no menu): dados, retenção, exportar JSON (`/account/export`: conta, usuários, eventos e metadados das fotos; sem arquivos, `device_hash`, caminhos ou tokens) e excluir a conta com senha + confirmação (apaga banco e `storage/.../tenants/{uuid}`; sessões e tokens de senha; anonimiza meta/IP dos logs antigos da conta; grava `account.deleted` sem dados pessoais). Exportar/excluir: só `owner` (moderador → 403).

**Retenção e minimização** — `php artisan privacy:enforce-retention` (diário, 03:17; `--dry-run` disponível):

- Evento com prazo vencido (`expires_at` = fim + `plans.retention_days`; teste de conta que não virou `active`: 7 dias após `trial_ends_at`): apaga fotos, miniaturas, prévias, ZIPs e a pasta do evento; apaga os registros das fotos (nome, textos, `device_hash`); exportações viram `expired`; o evento fica só com o registro mínimo (`purged_at`, sem `welcome_message`).
- Apaga registros `deleted` com mais de 24 h, pastas órfãs (tenant sem conta, evento sem registro, moldura sem registro) e o **IP de `audit_logs` com mais de 90 dias**.
- Logs nunca levam nome/e-mail de convidado nem tokens.

**Textos legais** — `/privacidade` e `/termos` (pt_BR/en em `lang/*/legal.php`, `?lang=` força o idioma), versão em `config('photoparty.privacy.legal_version')`. Cobrem: operador, papéis, dados, finalidades e bases legais, compartilhamento (nenhum terceiro; Hostinger como suboperador, região configurável), transferência internacional (cláusula padrão ou texto configurável), retenção, direitos (LGPD art. 18 / GDPR arts. 15-22), como exercer e prazos (15 dias LGPD / 30 dias GDPR), encarregado/DPO, ANPD e autoridade da UE (DPC na Irlanda), segurança, menores, incidentes, cookies e alterações. Os Termos incluem a cláusula de tratamento de dados (DPA simplificado: instruções, sigilo, segurança, suboperadores, eliminação ao fim, assistência com direitos e incidentes). Nada é inventado: dados vazios aparecem como "[a preencher]".
- **Aviso de revisão jurídica (decisão):** o admin logado vê em destaque "Este texto é um modelo e deve ser revisado por um advogado antes do uso comercial." e a lista de campos vazios; o público vê só uma nota discreta no fim ("Texto-modelo pendente de revisão jurídica"), que some com `PHOTOPARTY_LEGAL_REVIEWED=true`.

**Admin** — menu **Privacidade** (`/admin/privacy`): solicitações com prazos LGPD/GDPR (vencidas destacadas), marcar concluída/reabrir com anotação; **Configurações de privacidade** (`/admin/privacy/settings`, gravadas em `site_settings`): razão social, CNPJ/registro, endereço, e-mail de contato (vazio usa `PHOTOPARTY_PRIVACY_CONTACT_EMAIL`), encarregado/DPO e e-mail, país/região dos servidores, cláusula de transferência.

**Página do convidado em tela única (celular):** cada passo cabe na tela sem rolar a página (altura `100vh` → `100dvh` → `visualViewport` em `--gp-app-h`, `interactive-widget=resizes-content`): cabeçalho de 1 linha, área central flexível (câmera/prévia em `contain`) e barra de ações inferior com área segura. Moldura e filtro em tiras horizontais de miniaturas reais (foto do convidado composta). Textos longos e "Minhas fotos" rolam só por dentro. Verificado com Chromium headless em 360×640, 390×844 e 412×915, foto retrato e paisagem (`scrollHeight <= innerHeight` em todos os passos, inclusive com a altura reduzida simulando o teclado).

### Checklist do dono antes do uso comercial

- [ ] Revisão de `/privacidade` e `/termos` (pt_BR e en) por **advogado**; definir lei aplicável e foro nos Termos (hoje "[a definir]"); depois `PHOTOPARTY_LEGAL_REVIEWED=true`.
- [ ] Preencher em `/admin/privacy/settings`: **razão social**, **CNPJ/registro**, **endereço**, **e-mail de contato de privacidade** (ou `PHOTOPARTY_PRIVACY_CONTACT_EMAIL`), **encarregado/DPO** (nome e e-mail), **país/região dos servidores** contratados na Hostinger e, se necessário, a cláusula de transferência internacional.
- [ ] Confirmar com o advogado os prazos de retenção por plano (`plans.retention_days`) e o texto do aviso do convidado (ao mudar, trocar `photoparty.privacy.consent_version`).
- [ ] Configurar SMTP do Hostinger se quiser o aviso por e-mail de novas solicitações (senão, acompanhar em `/admin/privacy`).
- [ ] Definir o processo de atendimento: encaminhar ao organizador, confirmar identidade sem pedir documentos desnecessários, responder em até 15 dias (LGPD) / 30 dias (GDPR).
- [ ] Manter o cron do `schedule:run` ativo (a retenção depende dele).

## Roteiro de teste manual do ciclo completo

Pré-requisitos: `composer install`, `.env` com `APP_KEY`, `php artisan migrate --seed`, `npm install && npm run build` (ou `npm run dev`). Rode `php artisan serve --host=0.0.0.0` e use o IP da máquina na rede local (por exemplo `http://192.168.0.10:8000`), tanto no computador quanto no celular. Defina `PHOTOPARTY_PUBLIC_URL` com esse mesmo endereço para o QR e o link do convidado apontarem para a sua máquina, e rode `php artisan queue:work` e `php artisan schedule:work` em outros terminais se quiser os comandos agendados.

1. Crie uma conta em `/register` (a confirmação de e-mail está desligada) e entre.
2. Crie um evento com início no passado próximo/agora e fim daqui a 2 horas, moderação **prévia**. Salve e clique em **Publicar**.
3. Na página do evento clique em **Abrir telão**: abre `/screen/{token}` em nova aba (tela "aguardando as primeiras fotos" com o QR no canto). Use o botão **Tela cheia**.
4. No celular, abra o link do convidado (`/e/{slug}?t=…`, copie do painel ou escaneie o QR do telão), aceite o consentimento, tire/escolha uma foto, escolha moldura e filtro e envie. A página deve avisar "aguardando aprovação".
5. Volte ao painel, clique em **Moderação**: a foto aparece em Pendentes em até 5 s (o contador sobe). Aprove (individual ou selecionando várias e usando o lote).
6. Em até 5 s a foto aparece no telão (a mais nova entra antes do rodízio). Envie mais fotos e aprove-as.
7. Em Aprovadas, clique **Tirar do telão**: a foto some do telão em até 5 s. Em Rejeitadas, **Voltar ao telão** a traz de volta.
8. Teste a rede: desligue o Wi-Fi do telão por 1 minuto; ele continua girando e mostra o aviso de offline. Em **Gerar novo link do telão** o link antigo passa a dar 404 e o telão aberto mostra "link não está mais ativo".
9. Repita com moderação **posterior**: as fotos entram direto no telão e a aba padrão da moderação é Aprovadas.

Observação (celular em rede local sem HTTPS): o navegador bloqueia a câmera ao vivo (`getUserMedia` exige HTTPS ou `localhost`), mas o botão de câmera via `input capture` (abre o app de câmera do celular) funciona normalmente. Para testar a câmera ao vivo use HTTPS (túnel/certificado local) ou o ambiente Docker com `.test` e HTTPS.

## Decisões tomadas

1. **Isolamento.** `BelongsToAccount` filtra toda consulta por `account_id` do usuário autenticado. Usuário autenticado sem conta que não seja admin não vê nada; admin não é filtrado (necessário para o módulo 9 — a área do admin precisa de policies próprias e auditoria). Sem usuário autenticado (cron, fila, rotas por token do convidado/telão) não há filtro: nesses fluxos o tenant vem do evento localizado pelo token. `account_id` não é mass-assignable (a trait o remove do `$fillable`) e, com usuário autenticado, gravar em outra conta lança `LogicException`. A policy repete a checagem e usa `Response::denyAsNotFound()`.
2. **Tokens do evento.** `Str::random(64)`; no banco ficam o hash SHA-256 (`*_token_hash`, único, usado para localizar o evento) e uma cópia **criptografada com a `APP_KEY`** (`*_token_encrypted`, cast `encrypted`). O SPEC pede "salvos como hash", mas o organizador precisa reimprimir o QR a qualquer momento e o token só existe em texto puro na geração; por isso a cópia criptografada. Consequências: quem tiver o banco **e** a `APP_KEY` consegue ler os tokens; trocar a `APP_KEY` invalida a cópia (seria preciso regenerar os tokens). SHA-256 simples (e não bcrypt) porque o token tem alta entropia e precisa de busca indexada. Tokens nunca vão para logs nem para `toArray()`.
3. **Teste grátis.** Conta em `trial` com `trial_used_at` nulo = "teste disponível": pode criar **um** evento (`is_trial = true`, definido pelo servidor) de no máximo 2 h. Ao publicar, começa o período de 3 h da conta (`trial_used_at`/`trial_ends_at`); por isso a publicação exige que o evento termine dentro dessas 3 h. Depois disso não se cria nem publica mais nada até a conta virar `active`; `accounts:expire-trials` muda a conta para `trial_ended` quando o prazo acaba. Limite de 20 fotos em `Event::photoLimit()` — o upload (módulo 4) deve consultá-lo. Contas `active` respeitam `max_events_per_month` do plano.
4. **Estados do evento.** `published_at` (publicação) e `closed_at` (encerramento manual) guardam as ações do organizador; o status é derivado dos horários e de `expires_at` (fim efetivo + retenção: plano, ou 7 dias no teste). Só rascunhos podem ser excluídos (a limpeza de arquivos é do módulo 11); eventos encerrados não podem ser editados. Moderador visualiza eventos e modera fotos (`EventPolicy::moderate`), mas não gerencia o evento.
5. **Horários** usam `dateTime` (não `timestamp`) para evitar o `ON UPDATE CURRENT_TIMESTAMP` implícito de MySQL/MariaDB antigos, e são interpretados no fuso `APP_TIMEZONE`.
6. **UUID v4.** Trait `HasPublicUuid` com `Str::uuid()`; não usamos `HasUuids` porque desde o Laravel 12 ele gera UUID v7 (ordenado por tempo).
7. **Senha forte:** mínimo 10 caracteres, maiúsculas e minúsculas, números e símbolos. Sem `uncompromised()` (dependeria de API externa).
8. **Anti-robô sem serviço externo:** honeypot (`website`), rate limit de 5 cadastros/hora por IP, confirmação de e-mail obrigatória e lista local de domínios temporários em `config/disposable_email_domains.php` (subdomínios também bloqueados). A recuperação de senha responde igual exista ou não o e-mail.
9. **Alpine com CSP estrita.** Usamos o build `@alpinejs/csp` (sem `eval`), então a lógica fica em `Alpine.data()` em `resources/js/app.js` e o HTML só referencia propriedades e métodos. Nenhum CDN nem fonte externa.
10. **Verificação de e-mail** é enviada diretamente no cadastro (`sendEmailVerificationNotification`), sem disparar `Registered`, para não haver envio duplicado.
11. **Auditoria:** cadastro, login (sucesso/falha, com e-mail em hash), logout, redefinição de senha, criar/editar/publicar/encerrar/excluir evento, novo token do telão e fim do teste vão para `audit_logs`.
12. **Caminho das fotos.** Mantido o disco `local` do Laravel 13 (`storage/app/private`), então os arquivos ficam em `storage/app/private/tenants/...` (fora de `public/`). A rota automática `/storage/...` do disco local foi desligada (`'serve' => false`): imagens só saem por `/media`.
13. **Upload sem eventos de model.** A foto do convidado é salva com `Photo::withoutEvents()` e `account_id`/`event_id`/`uuid` atribuídos a partir do evento do token, porque o hook da `BelongsToAccount` recusaria gravar se o aparelho tiver um organizador de outra conta logado. Consultas do fluxo do convidado usam `withoutGlobalScopes()` pelo mesmo motivo. Observers futuros em `Photo::created` não disparam nesse fluxo.
14. **Limites em transação.** Contagem de fotos (evento e aparelho) e gravação acontecem dentro de `DB::transaction` com `lockForUpdate()` no evento, para envios simultâneos não furarem o limite.
15. **Rotas de mídia sem route model binding:** `{photo}` é resolvido no controller para que todo 404 passe pelo `GuardMediaNotFound`. URLs assinadas são relativas (`signed:relative`) para não depender do host/`APP_URL`.
16. **Filtros aceitos no upload:** `none`, `bw`, `vintage`, `sepia`, `vivid`, `warm`, `cool` (o front usa `vintage`; o config usa `sepia`).

## Pendências e observações

- Os testes PHP não foram executados no ambiente onde o código foi escrito (sem acesso ao Packagist). Rode `composer install` e `php artisan test`; os testes de QR precisam da extensão `gd`. Gere e versione o `composer.lock`.
- Migração do Laravel 11 para o 13 (o Composer bloqueia as versões 11 e 12 por avisos de segurança): config e arquivos base sincronizados com o skeleton 13 mantendo as customizações. Efeitos: sessão serializada em JSON (`config/session.php`) e cache sem desserializar objetos PHP (`serializable_classes => false`); nome do cookie de sessão e prefixo de cache passam a usar hífen (todos precisam entrar de novo); o middleware de CSRF agora se chama `PreventRequestForgery` e também confere o header `Sec-Fetch-Site`; o `APP_TIMEZONE` continua sendo lido do `.env` (o skeleton 13 fixa UTC, mantivemos o env); no `chillerlan/php-qrcode` 6 o formato é escolhido por `outputInterface`.
- Laravel 13 com PHP 8.4 permitiria Pest 5 (exige PHP 8.4 e PHPUnit 13); mantivemos Pest 4 por compatibilidade com PHP 8.3.
- Antes era: `composer.json` com `chillerlan/php-qrcode`, Pest e `ext-gd`; `package.json` com `@alpinejs/csp`.
- Galeria completa e exportação ZIP (módulo 7) ainda não implementadas; o telão e a moderação ao vivo (módulos 5-6) estão prontos.
- **PHP do servidor:** o upload aceita foto de até 3 MB; confira `upload_max_filesize` ≥ 4M e `post_max_size` ≥ 8M no PHP 8.3 do subdomínio.
- Textos do organizador nas molduras usam nome/data do evento e os `default` do config (edição por moldura ainda não implementada).
- Limpeza de arquivos órfãos (cron) e apagar arquivos ao excluir evento: módulo 11.
- Molduras personalizadas são ativadas por evento na tela Molduras (não no formulário do evento). Admin ainda não desativa moldura custom pela interface.
