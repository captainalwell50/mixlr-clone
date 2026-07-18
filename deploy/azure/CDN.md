# CDN path for ~1k listeners (Azure)

HLS is cache-friendly. Put **Azure Front Door** or **Azure CDN** in front of MediaMTX HLS so the VM is not the sole origin for every listener.

## Recommended shape

```
Listeners → Front Door / CDN → origin: https://stream.example.org/hls/...
Broadcasters (WHIP/RTMP) → VM directly (not via CDN)
Laravel app → VM (or same host)
```

1. Keep Caddy on the VM serving `/hls/*` → MediaMTX `:8888`.
2. Create Azure Front Door (or CDN) with origin = `stream.example.org`, path `/hls`.
3. Cache rules: cache `.m3u8` briefly (1–2s) and `.m4s`/`.ts` longer (aligned with segment duration).
4. Set Laravel:

```env
MEDIAMTX_HLS_PUBLIC_BASE=https://stream.example.org/hls
MEDIAMTX_HLS_CDN_BASE=https://<your-afd-endpoint>/hls
```

Listen/embed playlist URLs will use `MEDIAMTX_HLS_CDN_BASE` when set (`Stream::hlsPlaylistUrl()`).

5. Do **not** put WHIP (`/rtc`) or RTMP through the CDN.
6. Load-test with ~100 concurrent listeners before Sunday; scale Front Door SKU if needed.

## NSG reminder

CDN only helps download. Studio still needs **UDP/TCP 8189** to the VM for WebRTC ICE. RTMP needs **TCP 1935** (or tunnel via VPN if you prefer not to expose RTMP publicly).
