#!/bin/bash
# Clears compiled Blade cache for NativePHP desktop storage (macOS dev).
set -euo pipefail

for dir in \
  "$HOME/Library/Application Support/biometric-collector-dev/storage/framework/views" \
  "$HOME/Library/Application Support/ph.edu.icct.biometric-collector/storage/framework/views"
do
  if [[ -d "$dir" ]]; then
    rm -f "$dir"/*.php 2>/dev/null || true
  fi
done
