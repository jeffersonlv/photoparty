#!/usr/bin/env bash
# Instalação/atualização do Photo Party no Hostinger (photo.uailabs.net).
#
# Uso (via SSH), depois de enviar os arquivos para a pasta home (~) com scp:
#   bash ~/install.sh
#
# Espera encontrar na home (~):
#   - photoparty-release.tar.gz  (código + vendor + build, sem segredos)
#   - photoparty.env             (só na 1ª instalação: .env de produção; é apagado daqui após o uso)
#
# O que faz:
#   1. extrai o release em ~/domains/photo.uailabs.net/photoparty (fora da web)
#   2. preserva .env e storage/ de instalações anteriores
#   3. troca public_html por um link para photoparty/public (só a pasta public fica exposta)
#   4. migrations + seeders de produção (planos, molduras, página inicial) + caches
#   5. cria o administrador (pede a senha no terminal)
set -euo pipefail

PHP=/opt/alt/php83/usr/bin/php
SITE="$HOME/domains/photo.uailabs.net"
APP="$SITE/photoparty"
WEB="$SITE/public_html"
STAMP=$(date +%Y%m%d-%H%M%S)

[ -x "$PHP" ] || { echo "PHP 8.3 não encontrado em $PHP"; exit 1; }

RELEASE="$HOME/photoparty-release.tar.gz"
[ -f "$RELEASE" ] || { echo "Envie photoparty-release.tar.gz para ~ primeiro."; exit 1; }

echo "==> Extraindo release"
NEW="$SITE/photoparty-$STAMP"
mkdir -p "$NEW"
tar -xzf "$RELEASE" -C "$NEW"

# .env: reaproveita o atual; na 1ª vez usa o ~/photoparty.env enviado.
if [ -f "$APP/.env" ]; then
  cp -p "$APP/.env" "$NEW/.env"
  rm -f "$HOME/photoparty.env"
elif [ -f "$HOME/photoparty.env" ]; then
  mv "$HOME/photoparty.env" "$NEW/.env"
else
  echo "Nenhum .env encontrado: envie photoparty.env para ~."; exit 1
fi
chmod 600 "$NEW/.env"

# storage/: fotos, logs, sessões ficam entre versões.
if [ -d "$APP/storage" ]; then
  rm -rf "$NEW/storage"
  mv "$APP/storage" "$NEW/storage"
fi
mkdir -p "$NEW/storage/app/private" "$NEW/storage/framework/"{cache,sessions,views} "$NEW/storage/logs" "$NEW/bootstrap/cache"
chmod -R u+rwX "$NEW/storage" "$NEW/bootstrap/cache"

# Troca a versão ativa.
[ -d "$APP" ] && mv "$APP" "$SITE/photoparty-old-$STAMP"
mv "$NEW" "$APP"
rm -f "$RELEASE"

# public_html -> photoparty/public (o resto do código fica fora da web).
if [ ! -L "$WEB" ]; then
  echo "==> Trocando public_html por link para photoparty/public"
  [ -d "$WEB" ] && mv "$WEB" "$SITE/public_html-original-$STAMP"
  ln -s "$APP/public" "$WEB"
fi

cd "$APP"

if ! grep -q '^APP_KEY=base64:' .env; then
  echo "==> Gerando APP_KEY"
  "$PHP" artisan key:generate --force
fi

echo "==> Banco de dados"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=PlanSeeder --force
"$PHP" artisan db:seed --class=FrameSeeder --force
"$PHP" artisan db:seed --class=HomeSeeder --force

echo "==> Caches de produção"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

if ! "$PHP" artisan tinker --execute='exit(App\Models\User::where("role","admin")->exists() ? 0 : 1);' >/dev/null 2>&1; then
  echo "==> Criando administrador"
  read -r -p "E-mail do administrador: " ADMIN_EMAIL
  "$PHP" artisan photoparty:create-admin "$ADMIN_EMAIL"
fi

# Versões antigas: mantém só a última, para rollback.
ls -dt "$SITE"/photoparty-old-* 2>/dev/null | tail -n +2 | xargs -r rm -rf

echo
echo "Pronto: https://photo.uailabs.net"
echo "Cron (hPanel > Avançado > Cron Jobs), a cada minuto:"
echo "  $PHP $APP/artisan schedule:run"
