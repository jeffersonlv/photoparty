# Revisão de segurança pré-lançamento (módulo 12)

Data: 18/11/2026 · Versão revisada: `main` (afc8658) + alterações locais em andamento · Revisor: Claude (AppSec)

## 1. Escopo

- Aplicação Laravel 13.42 / PHP 8.3 em `photo.uailabs.net` (Hostinger compartilhado): área do organizador, admin, página do convidado, telão, mídia, molduras, exportação ZIP, LGPD/GDPR (minhas fotos, solicitação do titular, exportação e exclusão de conta), geolocalização.
- Todas as 96 rotas de `php artisan route:list --except-vendor` (inclui `/demo/photos`, ainda em desenvolvimento).
- Comandos agendados (`routes/console.php`), job `BuildEventExport`, scripts `deploy/install.sh` e `deploy/update.sh`, `public/.htaccess`.
- Dependências Composer e npm.
- Fora do escopo: configuração do servidor Hostinger (LiteSpeed/hPanel), e-mail SMTP real, revisão jurídica dos textos legais. A demonstração ao vivo da home e os filtros do convidado estavam sendo alterados por outro agente durante a revisão: foram lidos, mas não editados.

## 2. Método

1. **Revisão de código guiada pela OWASP Top 10:2025** (A01–A10), arquivo por arquivo: controllers, middlewares, policies, models (`$fillable`, global scope `BelongsToAccount`), serviços de upload/imagem/ZIP, views Blade e JS (sinks de XSS).
2. **Matriz de rotas**: para cada rota, autenticação/autorização, isolamento entre contas (404), validação, rate limit, CSRF e dados expostos (resumo na seção 4).
3. **Busca dirigida**: `{!! !!}`, `x-html`, `innerHTML`, SQL bruto, `redirect()->intended/back()`, `unserialize`, chamadas externas (SSRF), `Log::`/tokens, `lockForUpdate`, caminhos de arquivo.
4. **Dependências**: `npm audit --omit=dev` e `npm audit`; `composer audit` (ver SR-14).
5. **Scan dinâmico** contra o app rodando localmente com `APP_ENV=production`, `APP_DEBUG=false`, SQLite separado com seed (variáveis de ambiente, sem tocar no `.env` do projeto). O OWASP ZAP não pôde ser instalado (download do GitHub bloqueado pelo proxy desta sessão), então foi feito um **scan equivalente por script** (Python, fora do projeto) cobrindo: headers de segurança em todas as rotas GET públicas e anônimas, flags de cookies, métodos HTTP (TRACE/TRACK/OPTIONS/PUT/DELETE/PATCH/PROPFIND), 32 caminhos sensíveis (`/.env`, `/.git/config`, `/composer.json`, `/storage/logs/laravel.log`, `/vendor/`...), CSRF (POST sem token ⇒ 419), XSS refletido e SQLi básicos nos formulários públicos, enumeração no "esqueci a senha", open redirect, header Host, rate limit de login. Complemento com **Chromium headless** (Playwright) para detectar violações de CSP e erros de console nas páginas públicas, do convidado e do telão.
6. **Correções com teste Pest de regressão** (`tests/Feature/Security/PreLaunchHardeningTest.php`, 14 testes) e novo scan para confirmar.

## 3. Achados

Severidade: Crítico / Alto / Médio / Baixo / Informativo. Status: corrigido / aceito / pendente.

| ID | Sev. | OWASP | Descrição | Status | Arquivo(s) |
| --- | --- | --- | --- | --- | --- |
| SR-01 | Médio | A01 | **Open redirect** em `GET /locale/{locale}`: `back()` usa o header `Referer`; um link num site externo para `/locale/en` devolvia o visitante ao site externo (confirmado no scan: `302 https://evil.example/phish`). Agora só volta para página do próprio site; senão vai para `/`. | corrigido | `app/Http/Controllers/LocaleController.php` |
| SR-02 | Médio | A02 | **Envenenamento do link de redefinição de senha pelo header Host**: as URLs geradas usavam o Host do request (o scan viu `Host: evil.example` refletido). Em produção, as URLs agora saem sempre de `APP_URL` (`URL::forceRootUrl` + `forceScheme('https')`). | corrigido | `app/Support/ProductionHardening.php`, `app/Providers/AppServiceProvider.php` |
| SR-03 | Médio | A02/A07 | Cookie de sessão **sem `Secure`** dependia do `.env` (`.env.example` traz `SESSION_SECURE_COOKIE=false`); copiar o exemplo para produção deixaria a sessão trafegar sem a flag. Com `APP_ENV=production` e `APP_URL` https, `session.secure` é forçado. | corrigido | `app/Support/ProductionHardening.php` |
| SR-04 | Baixo | A02 | `APP_DEBUG=true` esquecido em produção exibiria stack trace. Agora é desligado no boot e registrado como `critical` no log. | corrigido | `app/Support/ProductionHardening.php` |
| SR-05 | Médio | A07 | Redefinir a senha **não derrubava sessões abertas** em outros aparelhos (quem tinha a senha antiga continuava logado). Agora apaga as sessões do usuário (driver `database`). | corrigido | `app/Http/Controllers/Auth/PasswordResetController.php` |
| SR-06 | Baixo | A07 | **Enumeração de e-mail por tempo de resposta**: o timebox padrão (200 ms) é menor que um bcrypt de custo 12 (~245 ms medidos), e o e-mail de reset saía por SMTP síncrono (segundos a mais só quando o e-mail existe). Timebox de login/reset subiu para 600 ms (`AUTH_TIMEBOX_US`) e o e-mail de reset vai pela fila, no idioma de quem pediu. | corrigido | `config/auth.php`, `app/Notifications/QueuedResetPassword.php`, `app/Models/User.php` |
| SR-07 | Baixo | A04/A01 | **Token de upload no `Referer`**: a página do convidado (`/e/{slug}?t=...`) usava `strict-origin-when-cross-origin`, então cada requisição ao próprio site levava a URL com o token (logs de acesso). Agora `/e/*` e qualquer URL com `?t=` respondem `Referrer-Policy: no-referrer` (o telão já fazia isso). | corrigido | `app/Http/Middleware/SecurityHeaders.php` |
| SR-08 | Baixo | A09 | Bloqueio de login por excesso de tentativas (`Lockout`) não ia para o log de segurança. Agora registra no canal `security` com IP e hash do e-mail (sem senha). | corrigido | `app/Providers/AppServiceProvider.php` |
| SR-09 | Baixo | A06 | **Condição de corrida** na criação de evento: "um evento de teste por conta" e o limite mensal do plano eram conferidos antes de gravar, sem trava; dois POST simultâneos furavam a regra. A gravação agora acontece numa transação com a conta travada (`lockForUpdate`) e a regra é conferida de novo. | corrigido | `app/Services/EventScheduler.php` (`createLocked`), `app/Http/Controllers/EventController.php` |
| SR-10 | Baixo | A02 | `X-Powered-By: PHP/8.3.x` exposto pelo próprio PHP (`expose_php`), apesar do middleware. Removido com `header_remove()` e no `.htaccess`. | corrigido | `app/Http/Middleware/SecurityHeaders.php`, `public/.htaccess` |
| SR-11 | Baixo | A02 | `.htaccess`: `-Indexes` só valia com `mod_negotiation`; arquivos estáticos (`/build`, `/frames`, `/fonts`) saíam sem `nosniff`/HSTS/`X-Frame-Options`; nenhuma regra explícita para arquivos ocultos. Corrigido (`Options -Indexes` global, bloqueio de `/.xxx` exceto `.well-known`, headers para estáticos via `FilesMatch`). | corrigido | `public/.htaccess` |
| SR-12 | Médio | A06 | **Demonstração ao vivo da home** (em desenvolvimento por outro agente): evento público com moderação `post` (aprovação automática); qualquer pessoa pode publicar uma imagem que aparece na página inicial por até 1 h. Já há limites por IP/hora, teto de fotos por hora, limpeza horária e toggle no admin. Recomendação: moderação `pre` com fila no admin, ou exibir na home só fotos tiradas pelo próprio visitante (cookie do aparelho), e um botão "ocultar agora" no admin. | corrigido: demo pareada e privada por visitante (código de 32 caracteres na sessão, QR com `&d=`, `photos.demo_pair` guarda o hash, `/demo/photos` só devolve as fotos do próprio visitante, upload sem código válido => 422); testes em `tests/Feature/Home/LiveDemoTest.php` | `app/Services/Demo/DemoEvent.php`, `routes/home.php` |
| SR-13 | Médio | A09 | A SPEC (seção 14, A09) prevê **alerta por e-mail via cron**; hoje os eventos ficam só em `audit_logs` e `storage/logs/security.log`, sem ninguém ser avisado. Recomendação: comando agendado (ex.: a cada 15 min) que resume lockouts, rajadas de 404 em `/media` e `Log::critical` e envia pelo SMTP do Hostinger. | pendente | `routes/console.php` |
| SR-14 | Baixo | A03 | `composer audit` **não pôde rodar** aqui: Packagist (e OSV.dev) bloqueados pelo proxy da sessão (`CONNECT 403`). Versões principais: laravel/framework 13.42.0, symfony/http-foundation 7.4.23, guzzlehttp/guzzle 8.5.0, league/flysystem 3.36.0, chillerlan/php-qrcode 6.0.1. `npm audit --omit=dev` e `npm audit`: **0 vulnerabilidades**. Rodar `composer audit` na máquina local antes do deploy. | pendente | `composer.lock` |
| SR-15 | Info | A10/A02 | Páginas de erro padrão do Laravel usam `<style>` inline (bloqueado pela CSP, página sem estilo) e textos em inglês. Novo `errors/minimal.blade.php` (CSS do build, sem inline, sem detalhes técnicos) + traduções pt_BR. | corrigido | `resources/views/errors/minimal.blade.php`, `lang/pt_BR.json` |
| SR-16 | Baixo | A08 | `deploy/update.sh` faz `git reset --hard origin/main` (branch, não tag). A SPEC pede deploy por tag Git. Recomendação: `update.sh <tag>` com `git fetch --tags` e checkout da tag (de preferência assinada), mantendo o pull da `main` só para emergência. | pendente | `deploy/update.sh` |
| SR-17 | Baixo | A07 | Sem 2FA para o admin (há colunas `two_factor_*`, mas o fluxo é pós-MVP). Mitigação: senha forte obrigatória, throttle, admin criado só por linha de comando. | aceito (pós-MVP) | — |
| SR-18 | Médio | A06 | **Geolocalização falsificável**: a posição vem do navegador e pode ser forjada (extensões, DevTools, GPS falso). Além disso, a resposta `outside` traz `distance_m`, o que permite estimar o local do evento por trilateração a quem tem o link do convidado. Aceito: é barreira de conveniência contra envio remoto casual, não controle de segurança; coordenadas nunca são gravadas; passe por sessão + aparelho + evento (30 min); rate limit 10/min por aparelho. | aceito | `app/Services/Geofence/LocationCheck.php` |
| SR-19 | Baixo | A04 | **Telão com token na URL** (`/screen/{token}`): pode aparecer em histórico do navegador do PC do telão e em logs. Mitigado: token de 64 caracteres guardado como hash SHA-256, `no-referrer`, `no-store`, só fotos aprovadas daquele evento com URL assinada de 10 min, e o organizador pode regenerar o token. | aceito | `app/Http/Controllers/Screen/ScreenController.php` |
| SR-20 | Baixo | A06 | Limite "fotos por convidado" e o rate limit por aparelho dependem do cookie anônimo `pp_device`; limpar o cookie ou usar aba anônima contorna. Teto por IP (60/min) e limite total do evento continuam no servidor (com `lockForUpdate`). | aceito | `app/Services/GuestDevice.php` |
| SR-21 | Baixo | A07 | O cadastro informa "e-mail já em uso" (enumeração). Aceito por usabilidade; mitigado pelo limite de cadastros por IP/hora e pelo honeypot. Login e "esqueci a senha" não revelam. | aceito | `app/Http/Requests/RegisterRequest.php` |
| SR-22 | Info | A07 | Força bruta distribuída (muitos IPs contra um e-mail) só é contida pelo limite por e-mail+IP e por IP. Sem bloqueio global por conta, de propósito (evita negação de serviço contra o dono da conta). Revisar com o alerta da SR-13. | aceito | `app/Http/Requests/LoginRequest.php` |
| SR-23 | Info | A01 | O moderador (mesma conta) pode baixar originais e gerar/baixar o ZIP (policy `view`). Coerente com "qualquer usuário da conta" no módulo 7, mas convém o dono do produto confirmar. | aceito (confirmar) | `app/Http/Controllers/Gallery/*` |
| SR-24 | Info | A07 | Confirmação de e-mail desligada no cadastro (decisão de 16/11/2026): enfraquece a regra "um teste por e-mail verificado". Religar com `PHOTOPARTY_REQUIRE_EMAIL_VERIFICATION=true` quando houver abuso. | aceito | `config/photoparty.php` |

**Totais:** Crítico 0 · Alto 0 · Médio 7 · Baixo 13 · Informativo 4 (24 achados). Corrigidos: 13. Pendentes: 3 (SR-13, SR-14, SR-16). Aceitos: 8.

### Pontos verificados sem achado

- **Isolamento (A01):** `BelongsToAccount` em todos os models de tenant (global scope; `account_id` removido de `$fillable` em runtime; hook de criação recusa outra conta); `scopeBindings()` em eventos/fotos/exportações/molduras; policies com `denyAsNotFound`; guest/telão sem global scope, mas o tenant vem sempre do evento resolvido pelo hash do token. Testes A×B existentes para evento, foto, mídia, moldura, ZIP, moderação, telão e "minhas fotos".
- **Mass assignment:** `$fillable` restritos (Event, Photo, Account, Frame, Plan, User sem `role`/`account_id`); `account_id`, `event_id`, `status`, tokens, `is_trial` sempre atribuídos no servidor.
- **Uploads:** `finfo` + `getimagesize` coerentes, dimensões exatas antes de decodificar (sem bomba de descompressão), reencode GD (EXIF/GPS e conteúdo anexado descartados), nome UUID gerado pelo servidor, limites de tamanho (3 MB/300 KB/2 MB), molduras PNG com dimensões exatas e redesenhadas.
- **Caminhos de arquivo / ZIP:** todo arquivo servido é conferido contra a pasta do tenant (`str_starts_with` + sem `..`); nomes dentro do ZIP são `{slug}-0001.jpg` gerados no servidor (sem zip slip); limpezas (`exports:purge-expired`, `privacy:enforce-retention`) só apagam sob `tenants/`.
- **XSS:** nenhum `{!! !!}`, `x-html`, `innerHTML`; dados para JS em blocos `application/json` com `JSON_HEX_*`; JS usa `textContent`/`createElement`; URLs em `href`/`src` são geradas pelo servidor; CSP `script-src 'self'` sem `unsafe-inline`/`unsafe-eval` (Alpine build CSP). Payloads refletidos nos formulários públicos saíram escapados no scan.
- **Injeção/SQL:** só Eloquent/bindings; os 3 trechos `*Raw` usam literais. Cursor do telão validado por regex (422). Nenhum `unserialize`, `exec` ou chamada HTTP externa (sem SSRF); sessão com serialização JSON.
- **CSRF:** todos os POST/PUT/DELETE no grupo `web` (scan: 419 sem token, inclusive upload e geolocalização do convidado); logout por POST.
- **Tokens:** `Str::random(64)`, guardados como SHA-256 (busca por hash, sem comparação em PHP); cópia criptografada com `APP_KEY` só para reimprimir o QR; comparação de `hash_equals` no passe de local e na moldura do convidado; tokens fora de logs (`dontFlash` de `t`/`token`).
- **Sessão/cookies:** `httponly`, `samesite=lax`, regeneração no login/cadastro, invalidação no logout e na exclusão de conta; cookie `pp_device` criptografado, `httponly`, `secure` fora do local.
- **Limites e corridas:** `lockForUpdate` no limite de fotos do evento/convidado, no limite de 20 molduras, no ZIP em andamento e na moderação; rate limits em login, cadastro, reset, upload, geolocalização, minhas fotos, telão, mídia, moderação, exportação de conta e exclusão de conta.
- **Exclusão e exportação de conta:** só o owner, exclusão exige senha atual + confirmação, apaga banco e arquivos do tenant, anonimiza auditoria; exportação JSON sem hashes, tokens ou IPs.
- **Erros (A10):** `APP_DEBUG=false` sem stack trace em 404/405/419/429; upload com falha apaga os arquivos e responde erro genérico; disco cheio ⇒ recusa clara.

## 4. Matriz de rotas (resumo)

| Grupo | Rotas | Autenticação / autorização | Isolamento | Rate limit |
| --- | --- | --- | --- | --- |
| Públicas (`/`, `/termos`, `/privacidade`, `/privacidade/solicitacao`, `/locale/{x}`, `/demo/photos`; mais `/up` do framework) | 7 | nenhuma; só dados públicos | — | `privacy-request`, `demo-screen` |
| Autenticação (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/logout`) | 12 | `guest`/`auth`; `signed` na verificação | — | login (e-mail+IP e IP), register, password-reset, 6/min |
| Organizador (`/dashboard`, `/account*`, `/events*`, `/frame-library*`) | 21 | `auth` + `verified` + `account` + policies (owner x moderador) | global scope + `scopeBindings` ⇒ 404 | exportação/exclusão de conta, upload de moldura, moderação |
| Moderação/galeria/ZIP (`/events/{event}/...`) | 8 | idem + `EventPolicy::moderate/view`, plano pago para baixar | idem | `moderation` |
| Admin (`/admin/*`) | 36 | `auth` + `admin` (não-admin ⇒ 404) + `verified`; ações auditadas | — | — (área restrita) |
| Convidado (`/e/{slug}...`) | 8 | token de upload (hash) + cookie do aparelho para "minhas fotos" | evento do token; foto de outro aparelho/evento ⇒ 404 | guest-page, guest-upload, guest-location, guest-mine, media |
| Telão (`/screen/{token}...`) | 2 | token do telão (hash) | só aprovadas daquele evento | screen |
| Mídia (`/media/...`) | 2 | `auth` + `PhotoPolicy` ou URL assinada (10 min) | global scope; rajadas de 404 logadas e bloqueadas | media + GuardMediaNotFound |

## 5. Riscos aceitos (resumo)

- **Geolocalização falsificável** e distância informada ao convidado (SR-18).
- **Token do telão e do convidado na URL** (SR-19, SR-07): inerente ao QR code e ao telão sem login; mitigado por hash no banco, `no-referrer`, `no-store`, regeneração e escopo mínimo.
- **Limite por convidado contornável** limpando o cookie (SR-20).
- **Enumeração no cadastro** (SR-21), **sem 2FA** no admin até o pós-MVP (SR-17), **sem confirmação de e-mail** (SR-24).

## 6. Recomendações para produção no Hostinger

1. **`.env` de produção** (`chmod 600`, fora de `public_html`, já feito pelo `install.sh`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://photo.uailabs.net`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, `MAIL_*` do SMTP do Hostinger, `PHOTOPARTY_PRIVACY_CONTACT_EMAIL`. Mesmo com erro no `.env`, SR-02/03/04 agora protegem em produção.
2. **Document root** apontando só para `photoparty/public` (link `public_html`, como no `install.sh`). Conferir no navegador: `/.env`, `/.git/config`, `/composer.json`, `/storage/logs/laravel.log` ⇒ 403/404.
3. **HTTPS forçado** no hPanel (certificado SSL + "Force HTTPS"); depois de estável, considerar `preload` no HSTS.
4. **Permissões:** pastas `755`, arquivos `644`, `.env` `600`; `storage/` e `bootstrap/cache/` graváveis só pelo usuário do site; nada de `777`.
5. **Caches:** `config:cache`, `route:cache`, `view:cache` a cada deploy (já nos scripts); `php artisan optimize:clear` antes.
6. **Cron** a cada minuto com o PHP 8.3 (`/opt/alt/php83/usr/bin/php artisan schedule:run`); conferir que `queue:work` processa a fila (o e-mail de reset agora depende dela).
7. **Backups:** backup diário do banco (hPanel) + cópia semanal de `storage/app/private/tenants` para fora do servidor; testar restauração uma vez antes do lançamento. Lembrar que backup também contém dados pessoais (retenção e acesso restrito).
8. **Rotação da `APP_KEY`:** nunca trocar sem planejar: ela criptografa sessões, cookies, a cópia do token de QR (`*_token_encrypted`) e o HMAC do `pp_device`. Se vazar: gerar nova chave, usar `APP_PREVIOUS_KEYS` com a antiga durante a transição, regenerar tokens de upload/telão dos eventos ativos (QR reimpresso) e forçar logout geral.
9. **Monitoramento:** acompanhar `storage/logs/security.log` (lockouts, rajadas em `/media`) e `laravel.log` (`critical`: disco cheio, debug ligado); implementar o alerta por e-mail (SR-13); monitor externo de disponibilidade em `/up`.
10. **Dependências:** `composer audit` e `npm audit` antes de cada deploy (SR-14); deploy por tag (SR-16).
11. **Scan:** repetir o OWASP ZAP baseline contra o ambiente Docker local (`hostinger-dev`) antes de cada versão, como pede a SPEC (aqui foi feito o scan equivalente por script).

## 7. Checklist de pré-lançamento

- [ ] `php artisan test` (inclui isolamento A×B e `tests/Feature/Security`) e `npx vitest run` verdes
- [ ] `npm run build` local e `public/build` atualizado no deploy
- [ ] `composer audit` (máquina local) e `npm audit` sem vulnerabilidades
- [ ] `.env` de produção revisado (item 1 da seção 6), `chmod 600`
- [ ] `public_html` ⇒ `photoparty/public`; `/.env`, `/.git/config`, `/composer.json`, `/storage/...` inacessíveis
- [ ] HTTPS forçado; `curl -I https://photo.uailabs.net` mostra CSP, HSTS, `X-Frame-Options: DENY`, `nosniff`, sem `X-Powered-By`
- [ ] Cookies `photo-party-session` e `XSRF-TOKEN` com `Secure`
- [ ] Página de erro (`/qualquer-coisa`) genérica, em português, sem stack trace
- [ ] Cron do `schedule:run` ativo; fila processando (testar "esqueci a senha" de ponta a ponta)
- [ ] Admin criado por `photoparty:create-admin` com senha forte; nenhum usuário de demonstração em produção
- [ ] SMTP do Hostinger testado; contato de privacidade preenchido em `/admin/privacy/settings`
- [ ] Backup do banco + `storage/app/private` configurado e restauração testada
- [x] Demonstração ao vivo da home: pareada por visitante (SR-12 corrigido); pode ser desligada no admin
- [ ] Alerta de segurança por e-mail (SR-13) ou rotina manual de leitura do `security.log`
- [ ] Textos legais revisados por advogado (`PHOTOPARTY_LEGAL_REVIEWED=true`)
- [ ] OWASP ZAP baseline contra o `hostinger-dev` sem alertas Médio/Alto
