# Pulse — macOS install (other computers)

Unsigned desktop builds often show **“Pulse is damaged and can’t be opened”** after download (Edge, Chrome, Google Drive). The app is not corrupt; macOS **Gatekeeper** and the download **quarantine** flag block it.

## Before you install

1. Confirm the file size matches the release (~267 MB ZIP, ~330 MB DMG for recent versions).
2. Prefer **Safari** or copy via USB if Drive keeps failing the download.

## Option A — ZIP (recommended for sharing)

1. Unzip `Pulse-x.y.z-arm64.zip`.
2. Open **Terminal** and run (adjust the path if needed):

```bash
xattr -cr ~/Downloads/Pulse.app
codesign --force --deep --sign - ~/Downloads/Pulse.app
cp -R ~/Downloads/Pulse.app /Applications/
xattr -cr /Applications/Pulse.app
```

3. **First launch:** Finder → Applications → **right-click Pulse → Open** → **Open** again.

## Option B — DMG

1. After download:

```bash
xattr -cr ~/Downloads/Pulse-*-arm64.dmg
```

2. Open the DMG and drag **Pulse** to **Applications**.
3. Then:

```bash
xattr -cr /Applications/Pulse.app
```

4. **Right-click → Open** on first launch (do not double-click the first time).

## If it still fails

- **System Settings → Privacy & Security** → scroll down → **Open Anyway** (appears after one blocked launch).
- Remove quarantine again: `xattr -cr /Applications/Pulse.app`
- Apple Silicon Mac only (`arm64`). Intel Macs need an x64 build if provided.

## For IT / developers

Production releases should use **Apple Developer ID** signing and notarization (`NATIVEPHP_NOTARIZE=1` with Apple credentials). Local builds use adhoc sign via `./scripts/fix-mac-pulse-codesign.sh` during `build-desktop.sh`.
