# Church Live Audio

Browser-based live audio for churches: go live from Studio (microphone → WHIP), congregation listens via Listen or an embed (HLS), recordings appear in Archive.

**Stack:** Laravel 12 (control plane) + MediaMTX (WHIP → LL-HLS + fMP4 recording) + Vite/Tailwind.

## Architecture

```
Broadcaster (Studio WHIP or OBS RTMP) --> MediaMTX --HLS (+ optional CDN)--> Listen / Embed
                                              | webhooks
                                              v
                                           Laravel (status, chat, discover, orgs, archive)
```

- **Channels** at `/c/{slug}` (branded org pages) and **Events** at `/e/{uuid}` (one link → live)
- Stream paths: `live/<uuid>` with per-stream `stream_key` for WHIP/OBS
- Studio: signed volunteer URL or org/platform admin; event “Go live”
- Discover, follow + email notify, hearts, listener count, auth chat, analytics
- Installable PWA (manifest + service worker) for creator and listener
- CDN: set `MEDIAMTX_HLS_CDN_BASE` (see [deploy/azure/CDN.md](deploy/azure/CDN.md))
- Registration is off by default (`REGISTRATION_ENABLED=false`)
- **SaaS mode:** enable registration, creator onboarding (church / radio / event), Paystack subscriptions, and creator home at `/home`

## Requirements

| Tool | Version |
|------|---------|
| PHP | **8.4+** (Composer platform check) |
| Composer | 2.x |
| Node.js | 20+ |
| Docker | for local MediaMTX |

## Local setup

```bash
composer setup          # install, .env, key, migrate, npm build
# or manually:
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
```

Seed creates admin (`ADMIN_EMAIL`, password `password`) and a demo church stream. Seed output prints Listen / Studio URLs.

### MediaMTX (local)

```bash
# Point webhooks at your Laravel app (port 8000 default)
export MEDIAMTX_WEBHOOK_URL=http://host.docker.internal:8000/api/webhooks/mediamtx
export MEDIAMTX_WEBHOOK_SECRET=dev-webhook-secret

# Match .env
# MEDIAMTX_WEBHOOK_URL=...
# MEDIAMTX_WEBHOOK_SECRET=dev-webhook-secret
# MEDIAMTX_AUTH_URL is used by MediaMTX → Laravel publish auth (see docker-compose)

docker compose up -d --build
php artisan serve
```

Browser public bases (defaults in `.env.example`):

- `MEDIAMTX_WEBRTC_PUBLIC_BASE=http://127.0.0.1:8889`
- `MEDIAMTX_HLS_PUBLIC_BASE=http://127.0.0.1:8888`

Recordings are written to `storage/app/mediamtx-recordings` (Docker bind-mount).

### Day-of local checklist

1. `docker compose up -d` and `php artisan serve`
2. Log in as admin → Streams → open stream → copy Listen / Studio links
3. Open Studio → allow mic → Go live
4. Confirm Listen shows **Live** and audio plays
5. After stop, Archive indexes segments when MediaMTX finishes a recording segment

## Production: Azure VM

Church v1 deploys on a **single Azure Linux VM** running Laravel + PHP-FPM, MediaMTX (Docker), and Caddy (TLS + `/hls` + `/rtc` reverse proxy). See **[deploy/azure/README.md](deploy/azure/README.md)** for the full runbook, NSG ports, ICE hosts, MySQL, cron, and Sunday checklist.

Do **not** put MediaMTX on Azure App Service alone — WebRTC needs UDP and custom ports. A VM (or this all-in-one VM) is the supported path.

## Key environment variables

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | Product name in UI |
| `APP_URL` | Public site URL |
| `REGISTRATION_ENABLED` | `false` for church default; `true` for SaaS self-serve signup |
| `PAYSTACK_PUBLIC_KEY` / `PAYSTACK_SECRET_KEY` | Paystack API keys (billing) |
| `PAYSTACK_PLAN_STARTER` / `PAYSTACK_PLAN_PRO` | Paystack plan codes from dashboard |
| `ADMIN_EMAIL` | Seeded admin |
| `MEDIAMTX_WEBRTC_PUBLIC_BASE` | Public WHIP base (e.g. `https://stream.example.org/rtc`) |
| `MEDIAMTX_HLS_PUBLIC_BASE` | Public HLS base (e.g. `https://stream.example.org/hls`) |
| `MEDIAMTX_WEBHOOK_URL` | `https://…/api/webhooks/mediamtx` (from MediaMTX container) |
| `MEDIAMTX_WEBHOOK_SECRET` | Bearer secret (required; empty → webhooks return 503) |
| `MEDIAMTX_PUBLISH_SECRET` | Optional shared secret for WHIP publish auth |
| `MEDIAMTX_AUTH_URL` | URL MediaMTX calls for auth (compose default: host Laravel) |
| `RECORDING_RETENTION_DAYS` | Laravel prune job (align with MediaMTX `recordDeleteAfter`) |

Scheduler (production cron):

```bash
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
composer test
# or
php artisan test
```

CI runs tests + frontend build (see `.github/workflows/ci.yml`).

## Admin links (day of service)

On each stream’s edit page:

- **Listen** — share with congregation
- **Embed** — iframe for the church website
- **Studio (signed)** — 24h volunteer link; treat as a secret
- **Open studio** — admin while logged in
