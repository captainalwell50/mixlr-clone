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

Suggested size: **Ubuntu 24.04**, idle **B2s**, peak **D2as_v7** (or B4ms). Public IP must stay **Static**. With CDN HLS, idle B2s is enough for the website + one Studio publish; scale up around services.

See **§7 Origin VM autoscale** below.

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
  php8.4-mysql php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip unzip git curl ffmpeg
sudo usermod -aG docker "$USER"
# log out/in for docker group
```

Install [Caddy](https://caddyserver.com/docs/install#debian-ubuntu-raspbian) and Composer.

## 3. App deploy

**Never rsync with `--delete` over `database/` or `storage/app/`.** That wiped production SQLite once. Use:

```bash
./deploy/sync-app.sh
```

Or rsync with `deploy/rsync-excludes.txt` (excludes `database.sqlite`, uploads, `.env`).

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

# Storage UTC; on-screen times Nigeria WAT (GMT+1). Also: sudo timedatectl set-timezone Africa/Lagos
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Africa/Lagos

REGISTRATION_ENABLED=false
ADMIN_EMAIL=admin@yourchurch.org

# SaaS / multi-tenant: set REGISTRATION_ENABLED=true and configure Paystack
# PAYSTACK_PUBLIC_KEY=pk_live_...
# PAYSTACK_SECRET_KEY=sk_live_...
# PAYSTACK_PLAN_STARTER=PLN_...
# PAYSTACK_PLAN_PRO=PLN_...
# Webhook URL in Paystack Dashboard: https://stream.yourchurch.org/webhooks/paystack

MEDIAMTX_WEBRTC_PUBLIC_BASE=https://stream.yourchurch.org/rtc
MEDIAMTX_HLS_PUBLIC_BASE=https://stream.yourchurch.org/hls
# Optional CDN for ~1k listeners (see CDN.md — Cloudflare or Azure):
# MEDIAMTX_HLS_CDN_BASE=https://cdn.yourchurch.org/hls
# LISTEN_PREFER_HLS=true
# LISTEN_HLS_AAC_SIDECAR=true
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
# SQLite must be writable by www-data (not merely group-readable):
sudo chown www-data:www-data database/database.sqlite
sudo chmod 660 database/database.sqlite
sudo chown "$USER":www-data database && sudo chmod 775 database
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

Ensure `webrtcAdditionalHosts` includes the public DNS/IP so browsers behind NAT can complete ICE, and set `webrtcLocalTCPAddress: :8189` (UDP alone is not enough when home networks block UDP).

Open NSG **UDP 8189** and **TCP 8189**, and host UFW (`ufw allow 8189/udp` + `8189/tcp`). Caddy only proxies HTTP WHIP/WHEP signaling; media uses 8189 to the VM IP.

## 5. Scheduler

```bash
sudo crontab -u www-data -e
# add:
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

## 5b. SaaS billing (Paystack)

1. Creators land on the **Free** plan after onboarding (1 stream, Studio go-live) so they can test without a card.
2. Create **Starter** and **Pro** plans in the [Paystack Dashboard](https://dashboard.paystack.com/#/plans) (monthly, NGN or your currency).
3. Set `REGISTRATION_ENABLED=true` and paste plan codes into `PAYSTACK_PLAN_*` in `.env`.
4. Run `php artisan db:seed --class=PlanSeeder` (or full `--seed`) so `plans` rows match (includes Free + paid).
5. In Paystack → Settings → Webhooks, add `https://<your-host>/webhooks/paystack` (events: `charge.success`, `subscription.create`, `subscription.disable`, `invoice.payment_failed`).
6. Signature verification uses `PAYSTACK_SECRET_KEY` (HMAC SHA512), matching Paystack’s docs.

## 6. Sunday go-live checklist

1. SSH: `docker compose ps` — MediaMTX healthy; `curl -fsS https://stream…/up` — Laravel OK.
2. Admin → Streams → confirm status **offline** before service.
3. Copy **Listen** link for congregation; copy **signed Studio** link for the volunteer (secret).
4. Volunteer: Studio → allow microphone → Go live → status **Live**.
5. Spot-check Listen on a phone (cellular) and a laptop.
6. After service: Stop in Studio; confirm Archive lists the recording; download from admin if needed.
7. If Studio fails ICE: verify NSG UDP 8189 and `webrtcAdditionalHosts`.

## 7. Origin VM autoscale (smaller when idle)

The VM cannot deallocate *itself* and then start again. A job **outside** the guest (Azure Automation, Cloud Shell, or `./deploy/azure/reconcile-vm-size.sh` on a laptop) polls:

`GET https://soundmix.live/api/ops/vm-capacity`  
Header: `Authorization: Bearer $AZURE_VM_CAPACITY_TOKEN`

Response includes `desired` (`idle`|`peak`) and `desired_sku`. Peak when a stream is live, an event is live/paused, a scheduled event is within 45 minutes, admin “hold peak”, or a show ended within the grace window. Otherwise idle.

### One-time Azure setup

1. Confirm the public IP is **Static** (script does this before resize).
2. Production `.env`:

```env
AZURE_VM_AUTOSCALE_ENABLED=true
AZURE_VM_RESOURCE_GROUP=<rg>
AZURE_VM_NAME=<vm>
AZURE_VM_SKU_IDLE=Standard_B2s
AZURE_VM_SKU_PEAK=Standard_D2as_v7
AZURE_VM_IDLE_GRACE_MINUTES=20
AZURE_VM_PEAK_LOOKAHEAD_MINUTES=45
AZURE_VM_CAPACITY_TOKEN=<long-random>
```

3. Import `deploy/azure/autoscale-runbook.ps1` into an **Azure Automation** account (system-assigned identity + **Virtual Machine Contributor** on the VM). Schedule every 10 minutes. Set variables `CapacityUrl`, `CapacityToken`, `ResourceGroup`, `VmName`.

Or run from a logged-in machine:

```bash
export AZURE_VM_CAPACITY_TOKEN=... AZURE_VM_RESOURCE_GROUP=... AZURE_VM_NAME=...
# DRY_RUN=1 ./deploy/azure/reconcile-vm-size.sh
./deploy/azure/reconcile-vm-size.sh
```

Admin → **Settings** shows desired idle/peak and can hold peak for N hours before a large service.

**Expect ~2–5 minutes of downtime** on each resize (Studio disconnects; go live again after the VM is back). Do not resize while a service is on air — the API will keep `desired=peak` until idle grace expires.

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Listen never goes Live | Webhook URL/secret; MediaMTX container has `curl`; Laravel `/api/webhooks/mediamtx` |
| Studio connects then silent | ICE: `webrtcAdditionalHosts`, `webrtcLocalTCPAddress`, UDP/TCP 8189 (NSG + UFW) |
| Publish rejected | Stream UUID exists; optional `MEDIAMTX_PUBLISH_SECRET` matches query/password; auth URL reachable from Docker |
| No Archive rows | `runOnRecordSegmentComplete` hook; shared recordings path |
| 503 on webhook | `MEDIAMTX_WEBHOOK_SECRET` empty in Laravel |
