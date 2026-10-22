# MediaMTX (Sound Mix Live)

Custom Patched Image: `soundmix/mediamtx:1.15.6-sm1` (aliased locally as `mixlr-mediamtx:local`)
Base Image: `bluenviron/mediamtx:1.15.6-ffmpeg`
Upstream Defect Reference: [bluenviron/mediamtx#6188](https://github.com/bluenviron/mediamtx/issues/6188)

---

## 1. WebRTC WHIP Audio Reorder Stall Fix (SoundMix P0)

### Defect Mechanism (Upstream v1.15.6)
On UDP WHIP ingestion, `internal/protocols/webrtc/incoming_track.go` initializes `rtpreceiver.Receiver` (from `github.com/bluenviron/gortsplib/v5 v5.2.2`) with `UnrealiableTransport: true` and leaves `BufferSize` unset (0), which defaults to 64 packets.

- In WebRTC, RTP packet retransmissions (NACK) are negotiated only for video, never for audio.
- WebRTC audio (Opus) is packetized at 20 ms frames (≈50 RTP packets/second).
- If a single RTP packet (sequence $N$) is lost or reordered in transit, `rtpreceiver.Receiver.reorder()` buffers subsequent packets ($N+1, N+2, \dots$) waiting for the missing packet until `relPos >= BufferSize` (64 packets).
- At 50 pkt/s, buffering 64 packets creates **~1.28 seconds of silence/starvation** on downstream WHEP listeners and HLS remuxers.
- When the 64-packet threshold is reached, all buffered packets are flushed in a single millisecond burst (~65 packets), overflowing listener jitter buffers and inducing sustained jitter buffer latency spikes (>1.0s).

### Mitigation (`soundmix/mediamtx:1.15.6-sm1`)
In `internal/protocols/webrtc/incoming_track.go`, `BufferSize` is explicitly configured to `4` specifically for audio tracks (`t.track.Kind() == webrtc.RTPCodecTypeAudio`), while leaving video at `64`:

```go
bufferSize := 64
if t.track.Kind() == webrtc.RTPCodecTypeAudio {
    bufferSize = 4
}
```

### Empirical Verification
- **Unpatched (BufferSize = 64)**: 1 lost packet $\rightarrow$ 63 packets held, **1.280s stall duration**, 64 packets released in burst.
- **Patched (BufferSize = 4)**: 1 lost packet $\rightarrow$ 3 packets held, **0.080s stall duration (60ms wait)**, 4 packets released in burst.
- **Out-of-order packet reordering**: Packets arriving within 3 slots (up to 60ms jitter) are reordered cleanly with zero loss.
- **Opus PLC / FEC**: Decoders conceal the 20ms loss seamlessly via native packet loss concealment (PLC) and in-band FEC (`useinbandfec=1`).

---

## 2. Paths & Architecture

| Path | Role |
|------|------|
| `live/<uuid>` | WHIP Opus publish, WHEP low-latency playback, MP4/fMP4 recordings |
| `live/<uuid>/aac` | In-container ffmpeg sidecar republishing AAC-LC for browser HLS / CDN fallback |

- `run-ready.sh` fires the Laravel presence webhook, then `exec`s ffmpeg (Opus → AAC-LC 256k/320k) into `/aac`.
- RTSP (`:8554`) is strictly bound internally for the local ffmpeg sidecar.
- WebRTC UDP/TCP is bound on `:8189`.

---

## 3. Maintenance Window Deployment Procedure

> **Note**: Do not auto-deploy during active live broadcasts. Execute during an approved maintenance window.

### Step 1: Pre-Deployment Health Check
Confirm the current container status and active sessions:
```bash
docker compose ps
docker compose logs --tail=50 mediamtx
```

### Step 2: Build Patched Image on VM Host
```bash
# Build soundmix/mediamtx:1.15.6-sm1 locally using Docker multi-stage build
docker compose build mediamtx
```
Verify the build version inside the newly created image:
```bash
docker run --rm mixlr-mediamtx:local --version
# Expected output: v1.15.6-sm1
```

### Step 3: Container Restart (Brief ~2-second window)
```bash
docker compose up -d --no-deps mediamtx
```

### Step 4: Post-Deployment Verification
1. Check process startup logs:
   ```bash
   docker compose logs --tail=30 mediamtx
   ```
2. Verify WebRTC listener endpoints respond:
   ```bash
   curl -I http://127.0.0.1:8889/
   ```
3. Monitor session metrics during test broadcast:
   - WHIP publisher audio packet delivery remains steady at ~50 pkt/s.
   - Any simulated packet loss triggers only momentary ~60ms PLC rather than a ~1.28s freeze.

---

## 4. Rollback Procedure (< 30 seconds)

If any unexpected regression occurs, rollback is instantaneous to the upstream `bluenviron/mediamtx:1.15.6-ffmpeg` image:

### Option A: Fast Dockerfile Revert
```bash
# 1. Temporarily point Dockerfile back to upstream image
cat << 'EOF' > deploy/mediamtx/Dockerfile
FROM bluenviron/mediamtx:1.15.6-ffmpeg
USER root
RUN apk add --no-cache curl
EOF

# 2. Rebuild and restart container
docker compose build mediamtx
docker compose up -d --no-deps mediamtx
```

### Option B: Direct Compose Image Override
```bash
docker tag bluenviron/mediamtx:1.15.6-ffmpeg mixlr-mediamtx:local
docker compose up -d --no-deps mediamtx
```
