# CDN path for ~1k listeners (Azure Front Door or Cloudflare)

HLS is cache-friendly. Put a CDN in front of **MediaMTX HLS only** so the VM is not the sole origin for every listener. Keep WHIP/WHEP (Studio + low-latency) on the origin.

## Recommended shape

```
Public listeners → CDN → origin: https://stream.example.org/hls/...
Studio / WHEP     → VM directly (/rtc) — never via CDN
OBS RTMP          → VM directly (:1935)
Laravel APIs      → VM (or same host) — never via CDN
```

1. Keep Caddy on the VM serving `/hls/*` → MediaMTX `:8888`.
2. Put CDN in front of `/hls` only (see Cloudflare or Azure below).
3. Cache rules: short TTL for `.m3u8`, longer for segments (aligned with ~4s `hlsSegmentDuration`).
4. Set Laravel:

```env
MEDIAMTX_HLS_PUBLIC_BASE=https://stream.example.org/hls
MEDIAMTX_HLS_CDN_BASE=https://cdn.example.org/hls

# Prefer HLS for public listen (auto-on when CDN base is set). WHEP stays as fallback.
# LISTEN_PREFER_HLS=true

# Point listen HLS at Opus→AAC sidecar (required for Studio WHIP + CDN in Chrome):
# LISTEN_HLS_AAC_SIDECAR=true
```

Listen/embed playlist URLs use `MEDIAMTX_HLS_CDN_BASE` when set (`Stream::hlsPlaylistUrl()`). With `LISTEN_HLS_AAC_SIDECAR=true`, the playlist path is `live/<uuid>/aac/index.m3u8`.

5. Do **not** CDN `/rtc` (WHIP/WHEP), RTMP, or Laravel `/api/*`.
6. Load-test with ~100 concurrent listeners before a big service; scale CDN SKU if needed.

## Cloudflare (recommended clicks)

You do **not** need to move the whole site DNS to Cloudflare if you only want HLS caching — a subdomain CNAME is enough.

### Option A — HLS subdomain (cleanest)

1. In Cloudflare, add zone for your domain (or use an existing zone).
2. DNS → **Add record**:
   - Type: `CNAME`
   - Name: `cdn` (→ `cdn.yourdomain.org`)
   - Target: `stream.example.org` (origin hostname that already serves `/hls`)
   - Proxy status: **Proxied** (orange cloud)
3. SSL/TLS → mode **Full** (or Full strict if origin has a valid cert for the origin host).
4. Caching → **Cache Rules** (or Configuration Rules) — create two rules, both matching only HLS:

**Rule 1 — playlists (short TTL)**

- If: hostname equals `cdn.yourdomain.org` AND URI Path ends with `.m3u8`
- Then: Eligible for cache, Edge TTL = **2 seconds**, Browser TTL = **bypass** / respect origin

**Rule 2 — segments (longer TTL)**

- If: hostname equals `cdn.yourdomain.org` AND (URI Path ends with `.m4s` OR `.ts` OR `.mp4`)
- Then: Eligible for cache, Edge TTL = **10–30 seconds** (≈ 2–7× segment duration)

5. Optional: Caching → **Configuration** → ensure HTML/API paths are not forced-cached. Only this CDN hostname should be used for media.
6. Laravel:

```env
MEDIAMTX_HLS_CDN_BASE=https://cdn.yourdomain.org/hls
LISTEN_PREFER_HLS=true
LISTEN_HLS_AAC_SIDECAR=true
```

7. Confirm origin still works without CDN: `https://stream.example.org/hls/live/<uuid>/aac/index.m3u8` while live.

### Option B — Same hostname via Cloudflare proxy

Only if the apex/www already sits on Cloudflare:

1. Proxy the hostname (orange cloud).
2. Add Cache Rules that match **`/hls/*` only**:
   - `/hls/*.m3u8` → Edge TTL ~2s
   - `/hls/*.{m4s,ts,mp4}` → Edge TTL ~10–30s
3. Explicitly **bypass cache** for `/rtc/*`, `/api/*`, `/studio*`, `/livewire*`, and HTML document paths.
4. Set `MEDIAMTX_HLS_CDN_BASE` to the same public host `/hls` base (or leave unset and rely on `MEDIAMTX_HLS_PUBLIC_BASE` if the proxied host is already the listen origin).

**Do not** enable Cloudflare proxy in front of WebRTC media ports (UDP/TCP 8189) — ICE must hit the VM.

### CLI note

Cloudflare can be configured with Terraform or the API if you have an API token + zone ID. This repo does not store those credentials; use the dashboard steps above unless you add tokens locally.

## Azure Front Door / CDN

1. Create Front Door (or CDN) with origin = `stream.example.org`, path `/hls`.
2. Cache `.m3u8` briefly (1–2s) and `.m4s`/`.ts` longer.
3. Set `MEDIAMTX_HLS_CDN_BASE=https://<your-afd-endpoint>/hls`.

## Opus / AAC (why CDN alone is not enough for Studio)

Studio WHIP publishes **Opus**. Chrome cannot reliably play Opus-in-HLS, so a CDN in front of the primary playlist does not help mass listen for Studio goes-live.

MediaMTX (ffmpeg image) remuxes each ready primary path to `live/<uuid>/aac` (AAC). Enable:

1. Deploy updated `docker/mediamtx/mediamtx.yml` + `hooks/run-ready.sh` and restart the MediaMTX container (`docker compose up -d --build`).
2. Set `LISTEN_HLS_AAC_SIDECAR=true` (playlist → `/aac/index.m3u8`).
3. Set CDN base + `LISTEN_PREFER_HLS=true` (or rely on auto-prefer when CDN base / sidecar is set).

| Publish method | Public listen (with sidecar + prefer HLS) | Studio monitor |
|----------------|-------------------------------------------|----------------|
| Studio WHIP (Opus) | HLS AAC via CDN (WHEP fallback) | WHEP on primary |
| OBS RTMP (AAC) | HLS (sidecar re-encodes AAC→AAC) | WHEP if codecs allow |

## NSG reminder

CDN only helps download. Studio still needs **UDP/TCP 8189** to the VM for WebRTC ICE. RTMP needs **TCP 1935** (or tunnel via VPN if you prefer not to expose RTMP publicly). Do **not** expose MediaMTX RTSP `:8554` publicly — it is for the in-container ffmpeg sidecar only.
