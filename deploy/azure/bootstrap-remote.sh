#!/usr/bin/env bash
# Run on the Azure VM as azureuser (or any sudo user in docker + www-data groups).
# Usage:
#   REPO_URL=https://github.com/OWNER/REPO.git APP_HOST=x.x.x.x.sslip.io bash bootstrap-remote.sh
set -euo pipefail

REPO_URL="${REPO_URL:?Set REPO_URL to the git clone URL}"
APP_HOST="${APP_HOST:?Set APP_HOST to the public hostname (e.g. IP.sslip.io)}"
APP_DIR="${APP_DIR:-/var/www/app}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

export DEBIAN_FRONTEND=noninteractive

echo "==> Installing base packages"
sudo apt-get update -y
sudo apt-get install -y \
  ca-certificates curl gnupg lsb-release software-properties-common \
  git unzip ufw docker.io docker-compose-v2 python3

echo "==> Installing PHP 8.4"
if ! php -v 2>/dev/null | grep -q 'PHP 8.4'; then
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt-get update -y
  sudo apt-get install -y \
    php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mysql \
    php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip php8.4-bcmath
fi

echo "==> Installing Node 20"
if ! command -v node >/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi

echo "==> Installing Composer"
if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi

echo "==> Installing Caddy"
if ! command -v caddy >/dev/null; then
  sudo apt-get install -y debian-keyring debian-archive-keyring apt-transport-https
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
  sudo apt-get update -y
  sudo apt-get install -y caddy
fi

sudo systemctl enable --now docker
sudo usermod -aG docker "$USER" || true

echo "==> Cloning / updating app"
sudo mkdir -p "$(dirname "$APP_DIR")"
if [[ ! -d "$APP_DIR/.git" ]]; then
  sudo rm -rf "$APP_DIR"
  sudo git clone "$REPO_URL" "$APP_DIR"
  sudo chown -R "$USER":www-data "$APP_DIR"
else
  git -C "$APP_DIR" pull --ff-only
fi

cd "$APP_DIR"

WEBHOOK_SECRET="$(openssl rand -hex 32)"
PUBLISH_SECRET="$(openssl rand -hex 24)"
APP_URL="https://${APP_HOST}"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force

# Production env (idempotent-ish replacements; keep APP_KEY)
APP_URL="$APP_URL" APP_HOST="$APP_HOST" ADMIN_EMAIL="$ADMIN_EMAIL" \
WEBHOOK_SECRET="$WEBHOOK_SECRET" PUBLISH_SECRET="$PUBLISH_SECRET" \
python3 <<'PY'
import os
from pathlib import Path

app_url = os.environ["APP_URL"]
app_host = os.environ["APP_HOST"]
replacements = {
    "APP_NAME": '"Church Live"',
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "APP_URL": app_url,
    "ADMIN_EMAIL": os.environ["ADMIN_EMAIL"],
    "REGISTRATION_ENABLED": "true",
    "LOG_LEVEL": "warning",
    "MEDIAMTX_WEBRTC_PUBLIC_BASE": f"{app_url}/rtc",
    "MEDIAMTX_HLS_PUBLIC_BASE": f"{app_url}/hls",
    "MEDIAMTX_RTMP_PUBLIC_BASE": f"rtmp://{app_host}:1935",
    "MEDIAMTX_WEBHOOK_URL": f"{app_url}/api/webhooks/mediamtx",
    "MEDIAMTX_WEBHOOK_SECRET": os.environ["WEBHOOK_SECRET"],
    "MEDIAMTX_PUBLISH_SECRET": os.environ["PUBLISH_SECRET"],
    "MEDIAMTX_AUTH_URL": f"{app_url}/api/mediamtx/auth",
}
lines = Path(".env").read_text().splitlines()
keys_seen = set()
out = []
for line in lines:
    if "=" in line and not line.strip().startswith("#"):
        k = line.split("=", 1)[0]
        if k in replacements:
            out.append(f"{k}={replacements[k]}")
            keys_seen.add(k)
            continue
    out.append(line)
for k, v in replacements.items():
    if k not in keys_seen:
        out.append(f"{k}={v}")
Path(".env").write_text("\n".join(out) + "\n")
print("Wrote .env production values")
PY

npm ci
npm run build

touch database/database.sqlite
php artisan migrate --force --seed

sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R ug+rwx storage bootstrap/cache database

# MediaMTX ICE host
APP_HOST="$APP_HOST" python3 <<'PY'
import os
import re
from pathlib import Path

host = os.environ["APP_HOST"]
p = Path("docker/mediamtx/mediamtx.yml")
text = p.read_text()
pattern = r"webrtcAdditionalHosts:\s*\n(?:\s*-\s*.*\n)*"
replacement = f"webrtcAdditionalHosts:\n  - {host}\n"
text2, n = re.subn(pattern, replacement, text, count=1)
if n == 0:
    if "webrtcAdditionalHosts:" in text:
        text2 = text.replace("webrtcAdditionalHosts:", f"webrtcAdditionalHosts:\n  - {host}", 1)
    else:
        text2 = text.rstrip() + f"\n\nwebrtcAdditionalHosts:\n  - {host}\n"
p.write_text(text2)
print("Updated webrtcAdditionalHosts")
PY

export MEDIAMTX_WEBHOOK_URL="${APP_URL}/api/webhooks/mediamtx"
export MEDIAMTX_WEBHOOK_SECRET="${WEBHOOK_SECRET}"
export MEDIAMTX_AUTH_URL="${APP_URL}/api/mediamtx/auth"
export MEDIAMTX_PUBLISH_SECRET="${PUBLISH_SECRET}"

# docker compose needs group; use sudo if fresh login hasn't applied docker group
if docker info >/dev/null 2>&1; then
  docker compose up -d --build
else
  sudo docker compose up -d --build
fi

echo "==> Configuring Caddy"
sudo tee /etc/caddy/Caddyfile >/dev/null <<EOF
${APP_HOST} {
	encode gzip zstd
	root * ${APP_DIR}/public
	php_fastcgi unix//run/php/php8.4-fpm.sock
	file_server
	try_files {path} {path}/ /index.php?{query}

	handle_path /hls/* {
		reverse_proxy 127.0.0.1:8888
	}

	handle_path /rtc/* {
		uri strip_prefix /rtc
		reverse_proxy 127.0.0.1:8889
	}
}
EOF

sudo systemctl enable --now php8.4-fpm
sudo systemctl enable --now caddy
sudo systemctl reload caddy || sudo systemctl restart caddy

echo "==> Scheduler cron"
CRON_LINE="* * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
EXISTING="$(sudo crontab -u www-data -l 2>/dev/null || true)"
if ! echo "$EXISTING" | grep -qF 'artisan schedule:run'; then
  printf '%s\n%s\n' "$EXISTING" "$CRON_LINE" | sudo crontab -u www-data -
fi

echo "==> Firewall (UFW) — Azure NSG is primary; keep local ports open"
sudo ufw allow OpenSSH || true
sudo ufw allow 80/tcp || true
sudo ufw allow 443/tcp || true
sudo ufw allow 8189/udp || true
sudo ufw allow 8189/tcp || true
sudo ufw allow 1935/tcp || true
sudo ufw --force enable || true

echo ""
echo "Deploy complete."
echo "  App URL:     ${APP_URL}"
echo "  Health:      ${APP_URL}/up"
echo "  Admin login: ${ADMIN_EMAIL} / password (from seeder)"
echo "  Secrets were written to ${APP_DIR}/.env"
