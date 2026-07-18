# Azure VM deploy (Church v1)

Run **Laravel + MediaMTX + Caddy on one Azure Linux VM**. This avoids Azure App Service limits (no WebRTC UDP / custom media ports) and keeps recordings on the same disk Laravel already reads.

## Topology

| Component | On the VM |
|-----------|-----------|
| Caddy | TLS termination; proxies `/hls` → MediaMTX `:8888`, `/rtc` → `:8889`, everything else → PHP-FPM |
| Laravel | `/var/www/app` via PHP 8.4-FPM |
| MediaMTX | Docker Compose (curl-enabled image + hooks) |
| DB | SQLite on small churches, or Azure Database for MySQL Flexible Server |
| Recordings | `storage/app/mediamtx-recordings` (Docker volume bind) |

Suggested size: **Ubuntu 24.04**, VM size **B2s** or larger, public IP, DNS A record to that IP.

## 1. Azure resources

1. Resource group + **Ubuntu 24.04 VM** (B2s+), SSH key auth.
2. **NSG** inbound:
   - `22` (SSH; restrict to your IP)
   - `80` / `443` (Caddy / HTTP-01)
   - `8189/UDP` and `8189/TCP` (WebRTC ICE — required for Studio from home networks)
   - `1935/TCP` (OBS/RTMP ingest; restrict by IP if possible)
3. For ~1k listeners, put Azure Front Door/CDN in front of `/hls` — see [CDN.md](CDN.md).
4. Optional: Azure Database for MySQL Flexible Server (private access or firewall allow VM IP).
5. DNS: `stream.yourchurch.org` → VM public IP.

## 2. Server packages

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2 php8.4-fpm php8.4-cli php8.4-sqlite3 \
  php8.4-mysql php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip unzip git curl
sudo usermod -aG docker "$USER"
# log out/in for docker group
```

Install [Caddy](https://caddyserver.com/docs/install#debian-ubuntu-raspbian) and Composer.

## 3. App deploy

Use the project branch `project/mixlr-clone` (not `main`):

```bash
sudo mkdir -p /var/www/app
sudo chown "$USER":www-data /var/www/app
git clone --branch project/mixlr-clone --single-branch <your-repo-url> /var/www/app
cd /var/www/app
# Or bootstrap:
# REPO_URL=<your-repo-url> REPO_BRANCH=project/mixlr-clone APP_HOST=<host> bash deploy/azure/bootstrap-remote.sh
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
npm ci && npm run build
```

Configure `.env` (example for same-host Caddy paths — see also [`../Caddyfile.example`](../Caddyfile.example)):

```env
APP_NAME="Your Church Live"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://stream.yourchurch.org

REGISTRATION_ENABLED=false
ADMIN_EMAIL=admin@yourchurch.org

MEDIAMTX_WEBRTC_PUBLIC_BASE=https://stream.yourchurch.org/rtc
MEDIAMTX_HLS_PUBLIC_BASE=https://stream.yourchurch.org/hls
# Optional CDN for ~1k listeners (see CDN.md):
# MEDIAMTX_HLS_CDN_BASE=https://<afd-endpoint>/hls
MEDIAMTX_RTMP_PUBLIC_BASE=rtmp://stream.yourchurch.org:1935

MEDIAMTX_WEBHOOK_URL=https://stream.yourchurch.org/api/webhooks/mediamtx
MEDIAMTX_WEBHOOK_SECRET=<long-random>
MEDIAMTX_PUBLISH_SECRET=<optional-long-random>
MEDIAMTX_AUTH_URL=https://stream.yourchurch.org/api/mediamtx/auth

DB_CONNECTION=sqlite
# or mysql Flexible Server credentials
```

```bash
php artisan migrate --force --seed
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

Point PHP-FPM + Caddy at `/var/www/app/public` using [`../Caddyfile.example`](../Caddyfile.example) (replace hostname and PHP socket).

## 4. MediaMTX on the VM

Copy production config and set ICE host to your **public hostname or IP**:

```bash
# In deploy/mediamtx/mediamtx.yml (or docker bind):
# webrtcAdditionalHosts: ['stream.yourchurch.org']
```

From the repo root (or a `/opt/mediamtx` copy of `docker-compose.yml` + `docker/` + `deploy/mediamtx`):

```bash
export MEDIAMTX_WEBHOOK_URL=https://stream.yourchurch.org/api/webhooks/mediamtx
export MEDIAMTX_WEBHOOK_SECRET=<same-as-.env>
export MEDIAMTX_AUTH_URL=https://stream.yourchurch.org/api/mediamtx/auth
export MEDIAMTX_PUBLISH_SECRET=<same-as-.env-if-set>

docker compose up -d --build
```

Ensure `webrtcAdditionalHosts` includes the public DNS/IP so browsers behind NAT can complete ICE.

Open NSG **UDP 8189** (and TCP 8189). Caddy only proxies HTTP WHIP/HLS signaling; media often uses UDP to the VM IP.

## 5. Scheduler

```bash
sudo crontab -u www-data -e
# add:
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Sunday go-live checklist

1. SSH: `docker compose ps` — MediaMTX healthy; `curl -fsS https://stream…/up` — Laravel OK.
2. Admin → Streams → confirm status **offline** before service.
3. Copy **Listen** link for congregation; copy **signed Studio** link for the volunteer (secret).
4. Volunteer: Studio → allow microphone → Go live → status **Live**.
5. Spot-check Listen on a phone (cellular) and a laptop.
6. After service: Stop in Studio; confirm Archive lists the recording; download from admin if needed.
7. If Studio fails ICE: verify NSG UDP 8189 and `webrtcAdditionalHosts`.

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Listen never goes Live | Webhook URL/secret; MediaMTX container has `curl`; Laravel `/api/webhooks/mediamtx` |
| Studio connects then silent | ICE: `webrtcAdditionalHosts`, UDP 8189 open |
| Publish rejected | Stream UUID exists; optional `MEDIAMTX_PUBLISH_SECRET` matches query/password; auth URL reachable from Docker |
| No Archive rows | `runOnRecordSegmentComplete` hook; shared recordings path |
| 503 on webhook | `MEDIAMTX_WEBHOOK_SECRET` empty in Laravel |
