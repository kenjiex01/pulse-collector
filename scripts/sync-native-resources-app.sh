#!/usr/bin/env bash
# Keeps vendor/nativephp/electron/resources/js/resources/app in sync with this
# Laravel project so Electron child processes and schedule:run find artisan commands.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$ROOT/vendor/nativephp/electron/resources/js/resources/app"
MARKER="$TARGET/app/Console/Commands/CollectBiometricLogsCommand.php"
WORKER="$TARGET/app/Console/Commands/RunBiometricAutoCollectCommand.php"

if [[ -f "$MARKER" && -f "$WORKER" ]]; then
  exit 0
fi

echo "Syncing Laravel app into NativePHP resources/app (first run or stale copy)..."

mkdir -p "$TARGET"

rsync -a --delete \
  --exclude 'vendor/nativephp/electron/resources/js/resources/app/' \
  --exclude 'node_modules/' \
  --exclude '.git/' \
  --exclude 'dist/' \
  --exclude 'build/' \
  --exclude 'storage/logs/' \
  --exclude 'storage/framework/cache/' \
  --exclude 'storage/framework/sessions/' \
  --exclude 'storage/framework/views/' \
  --exclude 'storage/framework/testing/' \
  --exclude 'database/*.sqlite' \
  --exclude 'database/*.sqlite-*' \
  "$ROOT/" "$TARGET/"

echo "NativePHP resources/app sync complete."
