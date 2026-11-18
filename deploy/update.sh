#!/usr/bin/env bash
# Atualiza o Photo Party no Hostinger a partir do GitHub (branch main).
#
# Uso (via SSH):  bash ~/domains/photo.uailabs.net/photoparty/deploy/update.sh
#
# Mesmo modelo do UaiLabs: o GitHub Actions compila o front e commita public/build,
# então aqui só precisa de git pull + composer install + migrations + caches.
# Usa a chave de deploy ~/.ssh/photoparty_deploy (Host github-photoparty no ~/.ssh/config).
set -euo pipefail

PHP=/opt/alt/php83/usr/bin/php
APP="$HOME/domains/photo.uailabs.net/photoparty"
REPO="git@github-photoparty:jeffersonlv/photoparty.git"
BRANCH="${1:-main}"

cd "$APP"

# Composer do Hostinger, rodando com o PHP 8.3 do site.
COMPOSER_BIN=$(command -v composer || true)
[ -n "$COMPOSER_BIN" ] || { echo "composer não encontrado no servidor"; exit 1; }
composer_run() { "$PHP" "$COMPOSER_BIN" "$@"; }

# 1ª vez: a pasta veio do pacote (sem .git). Vira um checkout do GitHub,
# mantendo .env, storage/ e vendor/ (não rastreados pelo Git).
if [ ! -d .git ]; then
  echo "==> Ligando a pasta ao repositório GitHub"
  git init -q
  git remote add origin "$REPO"
fi

echo "==> Baixando $BRANCH"
git fetch -q --depth 1 origin "$BRANCH"
PREV=$(git rev-parse -q --verify HEAD || echo "nenhum")
git reset -q --hard "origin/$BRANCH"
echo "    $PREV -> $(git rev-parse --short HEAD)"

[ -f public/build/manifest.json ] || { echo "public/build ausente: espere o GitHub Actions terminar o build e rode de novo."; exit 1; }

"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up || true' EXIT

echo "==> Dependências PHP"
composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Banco de dados"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=FrameSeeder --force
"$PHP" artisan db:seed --class=DemoSeeder --force

echo "==> Caches"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart

echo
echo "Atualizado: https://photo.uailabs.net"
