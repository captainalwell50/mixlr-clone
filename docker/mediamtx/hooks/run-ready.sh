#!/bin/sh
set -e
if [ -z "$WEBHOOK_URL" ] || [ -z "$WEBHOOK_SECRET" ]; then
  exit 0
fi
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
