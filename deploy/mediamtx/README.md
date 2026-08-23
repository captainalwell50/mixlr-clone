# MediaMTX (Sound Mix Live)

Image: `bluenviron/mediamtx:1.15.6-ffmpeg` (see `Dockerfile`) — includes `ffmpeg` for the AAC listen sidecar.

## Paths

| Path | Role |
|------|------|
| `live/<uuid>` | WHIP / RTMP publish, WHEP low-latency, recordings |
| `live/<uuid>/aac` | ffmpeg AAC republish for browser-safe HLS / CDN |

`run-ready.sh` fires the Laravel presence webhook, then `exec`s ffmpeg (Opus/AAC → AAC) into the `/aac` path. `runOnReadyRestart: yes` keeps the remux alive across brief drops.

RTSP (`:8554`) is for in-container remux only — do not map it in `docker-compose` or open it in the NSG.

## Enable for production

1. Deploy updated `mediamtx.yml` + hooks and restart: `docker compose up -d --build`
2. In Laravel `.env`:
   ```env
   LISTEN_HLS_AAC_SIDECAR=true
   LISTEN_PREFER_HLS=true
   # optional:
   MEDIAMTX_HLS_CDN_BASE=https://cdn.example.org/hls
   ```
3. Confirm while live: `/hls/live/<uuid>/aac/index.m3u8` plays in Chrome (hls.js).

Studio publish (WHIP Opus on the primary path) is unchanged.
