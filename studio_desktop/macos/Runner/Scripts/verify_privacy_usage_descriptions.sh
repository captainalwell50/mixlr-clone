#!/bin/sh
# Fail the build if privacy usage strings are missing from the processed Info.plist.
# Flutter merges Runner/Info.plist with INFOPLIST_KEY_* from the Xcode target; this
# catches merges that drop NSSpeechRecognitionUsageDescription / NSMicrophoneUsageDescription.
set -e
PLIST="${TARGET_BUILD_DIR}/${INFOPLIST_PATH}"
if [ ! -f "$PLIST" ]; then
  echo "error: processed Info.plist not found at $PLIST" >&2
  exit 1
fi
for key in NSSpeechRecognitionUsageDescription NSMicrophoneUsageDescription; do
  val="$(/usr/libexec/PlistBuddy -c "Print :${key}" "$PLIST" 2>/dev/null || true)"
  if [ -z "$val" ]; then
    echo "error: ${key} missing or empty in ${PLIST}" >&2
    exit 1
  fi
  echo "verified ${key}"
done
