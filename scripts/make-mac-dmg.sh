#!/usr/bin/env bash
# Creates Pulse macOS DMG from dist/mac-arm64/*.app
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -E '^NATIVEPHP_APP_VERSION=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r')"
PRODUCT_NAME="$(grep -E '^APP_NAME=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//')"
PRODUCT_NAME="${PRODUCT_NAME:-Pulse}"
APP="$ROOT/dist/mac-arm64/${PRODUCT_NAME}.app"
DMG="$ROOT/dist/${PRODUCT_NAME}-${VERSION}-arm64.dmg"

if [[ ! -d "$APP" ]]; then
  echo "ERROR: Missing $APP — run: cd \"$ROOT\" && ./scripts/build-desktop.sh mac"
  exit 1
fi

export TMPDIR="${TMPDIR:-/tmp}"
rm -f "$DMG"
hdiutil create -volname "${PRODUCT_NAME} ${VERSION}" -srcfolder "$APP" -ov -format UDZO "$DMG"
ls -lah "$DMG"
echo "OK: $DMG"
