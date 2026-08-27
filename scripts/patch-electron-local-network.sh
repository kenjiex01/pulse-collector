#!/usr/bin/env bash
# Ensures Electron dev app declares Local Network usage (macOS privacy prompt).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLIST="$ROOT/vendor/nativephp/electron/resources/js/node_modules/electron/dist/Electron.app/Contents/Info.plist"

if [[ ! -f "$PLIST" ]]; then
  exit 0
fi

MSG="Pulse needs access to devices on your local network (ZkTeco TCP port 4370)."

if /usr/libexec/PlistBuddy -c "Print :NSLocalNetworkUsageDescription" "$PLIST" &>/dev/null; then
  /usr/libexec/PlistBuddy -c "Set :NSLocalNetworkUsageDescription $MSG" "$PLIST" 2>/dev/null || true
else
  /usr/libexec/PlistBuddy -c "Add :NSLocalNetworkUsageDescription string $MSG" "$PLIST"
fi
