#!/bin/bash
cd "$(dirname "$0")/.."
open "x-apple.systempreferences:com.apple.settings.PrivacySecurity.extension?Privacy_LocalNetwork" 2>/dev/null \
  || open "x-apple.systempreferences:com.apple.preference.security?Privacy_LocalNetwork" 2>/dev/null \
  || open -a "System Settings"
osascript -e 'display alert "Local Network" message "Turn ON Pulse (or Electron) in the list, then restart the app."' 2>/dev/null || true
