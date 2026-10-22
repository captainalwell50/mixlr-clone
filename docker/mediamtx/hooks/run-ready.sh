#!/bin/sh
# Primary path ready: notify Laravel, then remux audio to live/<uuid>/aac for browser HLS/CDN.
set -e

if [ -n "$WEBHOOK_URL" ] && [ -n "$WEBHOOK_SECRET" ]; then
  BODY="{\"path\":\"$MTX_PATH\",\"event\":\"ready\"}"
  if command -v curl >/dev/null 2>&1; then
    curl -sS -X POST "$WEBHOOK_URL" \
      -H "Authorization: Bearer $WEBHOOK_SECRET" \
      -H "Content-Type: application/json" \
      -d "$BODY" || true
  elif command -v wget >/dev/null 2>&1; then
    wget -q -O- --header="Authorization: Bearer $WEBHOOK_SECRET" \
      --header="Content-Type: application/json" \
      --post-data="$BODY" "$WEBHOOK_URL" || true
  fi
fi

# Never remux the AAC sidecar into itself.
case "$MTX_PATH" in
  */aac) exit 0 ;;
esac

if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "run-ready: ffmpeg missing — AAC HLS sidecar disabled" >&2
  exit 0
fi

RTSP="${RTSP_PORT:-8554}"
SRC="rtsp://127.0.0.1:${RTSP}/${MTX_PATH}"
DST="rtsp://127.0.0.1:${RTSP}/${MTX_PATH}/aac"
if [ -n "${PUBLISH_SECRET:-}" ]; then
  DST="${DST}?pass=${PUBLISH_SECRET}"
fi

# Listen page prefers this AAC sidecar when HLS is selected. WHEP Listen
# plays Studio Opus directly. Keep source channel layout (no -ac).
AAC_BITRATE="${AAC_BITRATE:-320k}"
case "$AAC_BITRATE" in
  [1-9][0-9]*k) ;;
  *) AAC_BITRATE=320k ;;
esac

# Block here; MediaMTX runOnReadyRestart restarts on publisher drop / ffmpeg exit.
exec ffmpeg -hide_banner -loglevel warning -nostdin \
  -rtsp_transport tcp \
  -i "$SRC" \
  -map 0:a:0 -vn \
  -c:a aac -b:a "$AAC_BITRATE" -profile:a aac_low -ar 48000 -cutoff 20000 \
  -f rtsp -rtsp_transport tcp \
  "$DST"
