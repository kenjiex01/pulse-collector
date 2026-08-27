#!/bin/bash
# Opens Pulse in Terminal (GUI + TTY). Double-click in Finder.
cd "$(dirname "$0")/.."
unset ELECTRON_RUN_AS_NODE
unset CODESHELL_AGENT_STDIO 2>/dev/null || true

echo "Stopping old instances…"
pkill -f "biometric-collector-dev" 2>/dev/null || true
pkill -f "ISKOLARIS/biometric-collector/vendor/nativephp/electron" 2>/dev/null || true
pkill -f "ISKOLARIS/biometric-collector.*artisan native:serve" 2>/dev/null || true
sleep 2

exec ./scripts/run-native-dev.sh
