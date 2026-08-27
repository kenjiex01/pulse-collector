#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Cursor sets ELECTRON_RUN_AS_NODE; unset it so NativePHP/Electron opens as a GUI.
unset ELECTRON_RUN_AS_NODE
unset CODESHELL_AGENT_STDIO 2>/dev/null || true

bash "$(dirname "$0")/clear-native-view-cache.sh"
bash "$(dirname "$0")/sync-native-resources-app.sh"
bash "$(dirname "$0")/patch-electron-local-network.sh"
php artisan view:clear --quiet 2>/dev/null || true

export APP_PATH="$(pwd)"
export NODE_ENV=development
php artisan native:serve --no-dependencies --no-interaction "$@"
