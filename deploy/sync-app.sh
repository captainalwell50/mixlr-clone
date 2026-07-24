#!/usr/bin/env bash
# Safe production sync — never overwrites SQLite or uploaded media.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST="${DEPLOY_HOST:-azureuser@20.120.113.129}"
REMOTE="${DEPLOY_PATH:-/var/www/app}"

rsync -az \
  --exclude-from="$ROOT/deploy/rsync-excludes.txt" \
  "$ROOT/" \
  "$HOST:$REMOTE/"

ssh "$HOST" "cd $REMOTE && \
  sudo chown -R azureuser:www-data database storage bootstrap/cache && \
  sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \; && \
  sudo find storage bootstrap/cache -type f -exec chmod 664 {} \; && \
  sudo chmod 2775 database && \
  sudo chmod 664 database/database.sqlite 2>/dev/null || true && \
  (test -L public/storage || php artisan storage:link) && \
  php artisan migrate --force && \
  php artisan view:clear && \
  php artisan route:clear && \
  php artisan config:clear && \
  php artisan cache:clear"

echo "Deployed to $HOST:$REMOTE (database + storage preserved)."
