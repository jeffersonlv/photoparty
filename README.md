# Photo Party

Plataforma de fotos para eventos: o convidado escaneia um QR code, tira uma foto no navegador, aplica moldura/filtro e envia; as fotos aprovadas aparecem num telão. Especificação completa: `SPEC.md` (pasta de contratos do projeto).

Este repositório contém os módulos **1 (Base)** e **2 (Eventos)** do roadmap (SPEC, seção 16).

## Requisitos

- PHP 8.3 com as extensões `gd`, `mbstring`, `pdo_mysql` (e `pdo_sqlite` para os testes)
- Composer 2
- Node 20+ (só na máquina local, para gerar `public/build`)
- MySQL/MariaDB (produção e ambiente Docker local)

## Como rodar localmente

```bash
composer install
cp .env.example .env
php artisan key:generate
# ajuste DB_* no .env (ambiente Docker hostinger-dev ou MySQL local)
php artisan migrate --seed
npm install
npm run build
php artisan test
```

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

### Cron (Hostinger)

No hPanel, um cron a cada minuto:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O agendamento (`routes/console.php`) roda, nesta ordem: `accounts:expire-trials`, `events:refresh-statuses` e `queue:work --stop-when-empty --max-time=50 --tries=3`.

## O que está implementado

**Base (módulo 1)**

- Models `Plan`, `Account` (tenant), `User` (papéis `admin`, `owner`, `moderator`), `Event`, `Frame`, `Photo`, `Export`, `AuditLog`.
- Trait `App\Models\Concerns\BelongsToAccount`: global scope pela conta do usuário autenticado e preenchimento automático de `account_id` na criação.
- Migrations de `plans`, `accounts`, `users` (ajustada), `events`, `frames`, `event_frame`, `photos`, `exports`, `audit_logs`; chaves públicas UUID v4.
- Autenticação sem Breeze/Jetstream (Blade + Alpine): cadastro (nome, e-mail, senha forte, aceite de termos, honeypot, rate limit por IP, bloqueio de e-mail temporário), confirmação de e-mail obrigatória, login com throttle, logout, recuperação de senha.
- Estados da conta (`trial`, `trial_ended`, `active`, `past_due`, `cancelled`) e middleware `account.can-manage-events`.
- Middleware `SecurityHeaders` (CSP `self`, HSTS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy: camera=(self)`).
- Interface em pt_BR e en (`lang/`), troca de idioma em `/locale/{pt_BR|en}`.

**Eventos (módulo 2)**

- CRUD do organizador, ações publicar, encerrar, gerar novo link do telão, baixar QR (PNG e SVG).
- Estados `draft`, `scheduled`, `live`, `ended`, `expired` calculados pelos horários (`Event::computeStatus`) e mantidos em dia pelo comando `events:refresh-statuses`.
- Regras do teste grátis no servidor (`App\Services\EventScheduler`).
- `EventPolicy` + `scopeBindings()`; recurso de outra conta responde 404.

## Decisões tomadas

1. **Isolamento.** `BelongsToAccount` filtra toda consulta por `account_id` do usuário autenticado. Usuário autenticado sem conta que não seja admin não vê nada; admin não é filtrado (necessário para o módulo 9 — a área do admin precisa de policies próprias e auditoria). Sem usuário autenticado (cron, fila, rotas por token do convidado/telão) não há filtro: nesses fluxos o tenant vem do evento localizado pelo token. `account_id` não é mass-assignable (a trait o remove do `$fillable`) e, com usuário autenticado, gravar em outra conta lança `LogicException`. A policy repete a checagem e usa `Response::denyAsNotFound()`.
2. **Tokens do evento.** `Str::random(64)`; no banco ficam o hash SHA-256 (`*_token_hash`, único, usado para localizar o evento) e uma cópia **criptografada com a `APP_KEY`** (`*_token_encrypted`, cast `encrypted`). O SPEC pede "salvos como hash", mas o organizador precisa reimprimir o QR a qualquer momento e o token só existe em texto puro na geração; por isso a cópia criptografada. Consequências: quem tiver o banco **e** a `APP_KEY` consegue ler os tokens; trocar a `APP_KEY` invalida a cópia (seria preciso regenerar os tokens). SHA-256 simples (e não bcrypt) porque o token tem alta entropia e precisa de busca indexada. Tokens nunca vão para logs nem para `toArray()`.
3. **Teste grátis.** Conta em `trial` com `trial_used_at` nulo = "teste disponível": pode criar **um** evento (`is_trial = true`, definido pelo servidor) de no máximo 2 h. Ao publicar, começa o período de 3 h da conta (`trial_used_at`/`trial_ends_at`); por isso a publicação exige que o evento termine dentro dessas 3 h. Depois disso não se cria nem publica mais nada até a conta virar `active`; `accounts:expire-trials` muda a conta para `trial_ended` quando o prazo acaba. Limite de 20 fotos em `Event::photoLimit()` — o upload (módulo 4) deve consultá-lo. Contas `active` respeitam `max_events_per_month` do plano.
4. **Estados do evento.** `published_at` (publicação) e `closed_at` (encerramento manual) guardam as ações do organizador; o status é derivado dos horários e de `expires_at` (fim efetivo + retenção: plano, ou 7 dias no teste). Só rascunhos podem ser excluídos (a limpeza de arquivos é do módulo 11); eventos encerrados não podem ser editados. Moderador só visualiza eventos (moderação é o módulo 6).
5. **Horários** usam `dateTime` (não `timestamp`) para evitar o `ON UPDATE CURRENT_TIMESTAMP` implícito de MySQL/MariaDB antigos, e são interpretados no fuso `APP_TIMEZONE`.
6. **UUID v4.** Trait `HasPublicUuid` com `Str::uuid()`; não usamos `HasUuids` porque no Laravel 11 ele gera UUID ordenado por tempo.
7. **Senha forte:** mínimo 10 caracteres, maiúsculas e minúsculas, números e símbolos. Sem `uncompromised()` (dependeria de API externa).
8. **Anti-robô sem serviço externo:** honeypot (`website`), rate limit de 5 cadastros/hora por IP, confirmação de e-mail obrigatória e lista local de domínios temporários em `config/disposable_email_domains.php` (subdomínios também bloqueados). A recuperação de senha responde igual exista ou não o e-mail.
9. **Alpine com CSP estrita.** Usamos o build `@alpinejs/csp` (sem `eval`), então a lógica fica em `Alpine.data()` em `resources/js/app.js` e o HTML só referencia propriedades e métodos. Nenhum CDN nem fonte externa.
10. **Verificação de e-mail** é enviada diretamente no cadastro (`sendEmailVerificationNotification`), sem disparar `Registered`, para não haver envio duplicado.
11. **Auditoria:** cadastro, login (sucesso/falha, com e-mail em hash), logout, redefinição de senha, criar/editar/publicar/encerrar/excluir evento, novo token do telão e fim do teste vão para `audit_logs`.

## Pendências e observações

- Os testes não foram executados no ambiente onde o código foi escrito (sem acesso ao Packagist). Rode `composer install` e `php artisan test`; os testes de QR precisam da extensão `gd`.
- `package.json` ganhou `@alpinejs/csp`; `composer.json` ganhou `chillerlan/php-qrcode` (^5), `pestphp/pest` e `pestphp/pest-plugin-laravel` (dev) e a exigência `ext-gd`. Gere e versione `composer.lock` e `package-lock.json`.
- No Laravel 11 o disco `local` aponta para `storage/app/private`; o módulo 4 deve decidir se o caminho dos tenants fica em `storage/app/private/tenants/...` ou ajustar o disco para bater com o SPEC (`storage/app/tenants/...`).
- Rotas do convidado (`/e/{slug}`), telão (`/screen/{token}`), mídia, moderação e exportação estão só como comentários em `routes/web.php`. Use `Event::whereUploadToken()` / `Event::whereScreenToken()` para localizar o evento.
- Molduras no formulário do evento: módulo 8 (a tabela `event_frame` já existe).
