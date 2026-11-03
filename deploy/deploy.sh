#!/usr/bin/env bash
# Uso na VPS: cd /var/www/erp-netorodas && ./deploy/deploy.sh
set -euo pipefail
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction || true   # opcional: o app roda sem vendor
php bin/migrate.php
echo "Deploy concluido."
