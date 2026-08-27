#!/usr/bin/env bash
# Adhoc re-sign Pulse.app so Gatekeeper on other Macs does not show "damaged".
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PRODUCT_NAME="$(grep -E '^APP_NAME=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//')"
PRODUCT_NAME="${PRODUCT_NAME:-Pulse}"
APP="$ROOT/dist/mac-arm64/${PRODUCT_NAME}.app"

if [[ ! -d "$APP" ]]; then
  echo "    NOTE: No $APP — skip Mac codesign fix"
  exit 0
fi

echo "==> Adhoc re-signing ${PRODUCT_NAME}.app (Gatekeeper / other Mac installs)"
codesign --force --deep --sign - "$APP"
xattr -cr "$APP"

if codesign --verify --deep --strict "$APP" 2>/dev/null; then
  echo "    OK codesign verify passed"
else
  echo "WARNING: codesign verify reported issues (app may still run after xattr on target Mac)"
fi
