@php
    /** @var array{current_version: string, latest_version: string, platform: string, filename: string, download_url: string}|null $desktopInstallerUpdate */
    $desktopInstallerUpdate = $desktopInstallerUpdate ?? null;
@endphp

@if (! empty($desktopInstallerUpdate))
    <div
        id="desktop-installer-update-modal"
        class="desktop-update-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="desktop-installer-update-title"
        data-desktop-installer-update
        data-desktop-installer-force
    >
        <div class="desktop-update-card">
            <h2 id="desktop-installer-update-title">Update required</h2>
            <p>
                You must install the latest Pulse before continuing. Installed
                <strong>v{{ $desktopInstallerUpdate['current_version'] }}</strong>
                → required
                <strong>v{{ $desktopInstallerUpdate['latest_version'] }}</strong>.
            </p>
            <p class="muted" style="margin-top:0.75rem;">
                Download <code>{{ $desktopInstallerUpdate['filename'] }}</code>,
                quit this app, then run the installer.
            </p>
            <p class="muted" style="margin-top:0.75rem;">
                Local SQLite data on this computer is kept. After you open the new version, pending
                migrations run automatically.
            </p>

            <div
                class="desktop-update-progress"
                data-desktop-installer-progress
                hidden
                aria-live="polite"
            >
                <div class="desktop-update-progress-label">
                    <span data-desktop-installer-progress-label>Preparing download…</span>
                    <strong data-desktop-installer-progress-pct>0%</strong>
                </div>
                <div class="desktop-update-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-desktop-installer-progress-bar>
                    <div class="desktop-update-progress-fill" data-desktop-installer-progress-fill style="width:0%"></div>
                </div>
                <p class="muted desktop-update-progress-meta" data-desktop-installer-progress-meta></p>
            </div>

            <div style="margin-top:1.25rem;">
                <a
                    href="{{ $desktopInstallerUpdate['download_url'] }}"
                    class="btn"
                    style="width:100%; text-align:center; box-sizing:border-box;"
                    data-desktop-installer-download
                    data-desktop-installer-filename="{{ $desktopInstallerUpdate['filename'] }}"
                >
                    Download update
                </a>
                <p class="muted" style="margin:0.65rem 0 0; text-align:center; font-size:0.8rem;" data-desktop-installer-download-status hidden></p>
            </div>
        </div>
    </div>
@endif
