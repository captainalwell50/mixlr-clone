# Storage architecture (R2 + Google Drive)

Recommended path for Live Mix cost control.

## What goes where

| Content | Default | Cost-saving option |
|---|---|---|
| Live recordings (MediaMTX fMP4) | VM disk | **Cloudflare R2 / S3** (`OBJECT_STORAGE_ENABLED=true`) |
| Studio audio library | Platform disk / R2 | **Google Drive BYO** (creator’s quota) |
| Gallery / backgrounds | VM `public` disk | unchanged for now |

Drive files **do not** count toward plan `storage_bytes`. Platform library + recordings do.

## Plan quotas (`plans.limits.storage_bytes`)

| Plan | Platform storage |
|---|---|
| Free | 2 GB |
| Starter | 25 GB |
| Pro | 100 GB |

Re-seed plans after deploy: `php artisan db:seed --class=PlanSeeder`

## Phase 1 — Cloudflare R2 (recordings)

1. Create an R2 bucket + API token (Object Read & Write).
2. On the app server `.env`:

```env
OBJECT_STORAGE_ENABLED=true
OBJECT_STORAGE_DISK=s3
OBJECT_STORAGE_PREFIX=live-mix
OBJECT_STORAGE_DELETE_LOCAL_AFTER_SYNC=false

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-bucket
AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

3. Migrate + clear caches:

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart   # if using a queue worker
```

4. Backfill existing local recordings:

```bash
php artisan recordings:sync-pending --sync
```

5. After you confirm archive playback works, optionally:

```env
OBJECT_STORAGE_DELETE_LOCAL_AFTER_SYNC=true
```

Use a queue worker in production (`QUEUE_CONNECTION=database` or `redis`) so large uploads don’t block webhooks. With `sync`, uploads run inline.

## Phase 2 — Google Drive (studio library)

1. Google Cloud Console → OAuth client (Web) with redirect:

`https://YOUR_DOMAIN/integrations/google-drive/callback`

2. Enable **Google Drive API**.

3. `.env`:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_DRIVE_REDIRECT_URI="${APP_URL}/integrations/google-drive/callback"
```

4. In Studio → Audio library → **Connect Drive**.  
   Creates a **Live Mix Audio** folder. Upload destination **Google Drive** or **Import Drive** by file ID.

## Ops checklist

- `recordings:prune` still runs daily (deletes local + object keys past retention).
- Monitor R2 usage vs plan quotas; raise `storage_bytes` per plan as needed.
- Keep MediaMTX recording on local disk for ingest; object storage is the durable archive.
