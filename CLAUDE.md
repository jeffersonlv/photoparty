# Photo Party — instruções para o Claude Code

Plataforma de fotos para eventos: o convidado escaneia um QR code, tira uma foto no navegador, aplica moldura/filtro e envia; as fotos aprovadas aparecem num telão. A especificação completa está em `SPEC.md` — leia antes de cada tarefa.

- Domínio: `photo.uailabs.net`
- Pasta local: `C:\Users\jeffe\CLAUDE\photoparty`
- Idioma do código: inglês (nomes de classes, tabelas, rotas). Idioma da interface e dos comentários de negócio: português do Brasil, com suporte a inglês via arquivos de tradução do Laravel.

## Stack (não trocar sem perguntar)

- PHP 8.3 + Laravel 11, MySQL, Blade + Alpine.js, Vite (build feito localmente).
- Sem Node no servidor: o build (`npm run build`) roda na máquina local e só `public/build` vai para o servidor.
- Hospedagem: **só o Hostinger** (hospedagem compartilhada). Proibido depender de serviços externos: nada de Redis, WebSocket/Reverb, S3/GCS, CDN, Google Fonts, captcha externo, analytics de terceiros, APIs de QR code.
  - Fila: driver `database`, processada pelo cron (`schedule:run` a cada minuto chama `queue:work --stop-when-empty --max-time=50`).
  - Tempo real: polling (telão a cada 5 s, moderação a cada 5 s).
  - Storage: disco local, `storage/app/tenants/...`.
  - E-mail: SMTP do Hostinger.
  - Pagamento: fora do MVP (admin ativa plano manualmente).
- Ambiente local: Docker em `C:\Users\jeffe\CLAUDE\ambienteDev\Ambiente de Desenvolvimento\hostinger-dev`, que espelha o Hostinger (vhosts `.test`).

## Regras invioláveis

1. **Isolamento entre organizadores (tenants).** Nenhum organizador pode ver, listar, baixar ou adivinhar foto, evento, moldura ou ZIP de outro.
   - Todo model de tenant usa a trait `BelongsToAccount` (global scope por `account_id`).
   - `account_id` e `event_id` **nunca** vêm do request; sempre do usuário autenticado ou do evento resolvido pelo token.
   - Toda rota de recurso tem policy. Recurso de outro tenant responde **404** (não 403).
   - UUID v4 aleatório nas chaves públicas (`Str::uuid()`), nunca ID sequencial em URL.
   - Nenhuma foto fica em `public/`. Imagens saem só pela rota `/media/{photo}` com policy ou URL assinada com expiração.
   - Toda feature nova que toca fotos/eventos precisa de teste de isolamento (conta A tenta acessar recurso da conta B → 404).
2. **OWASP Top 10:2025** é checklist obrigatório (ver `SPEC.md`). Em especial: Eloquent/bindings sempre, Blade `{{ }}` sempre (nunca `{!! !!}` com dado de usuário), upload validado com `finfo` + `getimagesize` e reencodado com GD, rate limiting em login/cadastro/upload, `APP_DEBUG=false` em produção.
3. **Regras de negócio vivem no servidor.** Janela do evento, limite de fotos, plano/teste: o front só exibe; o servidor decide e recusa.
4. **Imagem é processada no celular** (compressão ~2000 px JPEG 0,8 + miniatura ~400 px). O servidor valida, reencoda com GD e grava. Nada de processamento pesado síncrono.

## Como trabalhar

- Um módulo por sessão, na ordem do roadmap em `SPEC.md`.
- Escreva testes (Pest) junto com o código; rode `php artisan test` antes de dizer que terminou.
- Antes de adicionar pacote Composer/npm, pergunte. Rode `composer audit` e `npm audit` ao adicionar.
- Migrations sempre reversíveis. Seeds de demonstração com duas contas (A e B) para testar isolamento.
- Não commitar `.env`, `storage/`, `vendor/`, `node_modules/`.
- Commits pequenos, mensagem em inglês no imperativo.
