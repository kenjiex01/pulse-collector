#!/bin/bash
cd "$(dirname "$0")/.."
unset ELECTRON_RUN_AS_NODE
unset CODESHELL_AGENT_STDIO 2>/dev/null || true
bash "$(dirname "$0")/clear-native-view-cache.sh"
php artisan view:clear --quiet 2>/dev/null || true
exec ./scripts/run-native-dev.sh
