#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/.."

# NativePHP excludes the top-level `packages/` folder from desktop builds.
# Keep the ZkTeco client under lib/ so it is bundled into the installer.
if [[ -f lib/zkteco-php/composer.json ]]; then
  echo "lib/zkteco-php already present."
  exit 0
fi

mkdir -p lib
tmp="$(mktemp)"
curl -fsSL -o "$tmp" https://github.com/msaied/zkteco-php/archive/refs/heads/main.zip
unzip -q "$tmp" -d lib
rm -f "$tmp"
rm -rf lib/zkteco-php
mv lib/zkteco-php-main lib/zkteco-php
echo "Fetched msaied/zkteco into lib/zkteco-php. Run: composer dump-autoload -o"
