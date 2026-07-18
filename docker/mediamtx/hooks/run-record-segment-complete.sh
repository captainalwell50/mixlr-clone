#!/bin/sh
if [ -z "$WEBHOOK_URL" ] || [ -z "$WEBHOOK_SECRET" ]; then
  exit 0
fi

case "$MTX_SEGMENT_PATH" in
  /recordings/*) REL="${MTX_SEGMENT_PATH#/recordings/}" ;;
  *) REL="$MTX_SEGMENT_PATH" ;;
esac

DUR="${MTX_SEGMENT_DURATION:-}"

SIZE_JSON="null"
if [ -f "$MTX_SEGMENT_PATH" ]; then
  S=$(wc -c < "$MTX_SEGMENT_PATH" | tr -d ' ')
  case "$S" in
    '' | *[!0-9]*) SIZE_JSON="null" ;;
    *) SIZE_JSON="$S" ;;
  esac
fi

BODY="{\"event\":\"record_segment_complete\",\"path\":\"${MTX_PATH}\",\"segment_relative\":\"${REL}\",\"duration_raw\":\"${DUR}\",\"size_bytes\":${SIZE_JSON}}"

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
