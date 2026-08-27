<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Desktop installer updates (S3)
    |--------------------------------------------------------------------------
    |
    | On desktop boot NativePHP AutoUpdater checks GitHub Releases
    | (kenjiex01/pulse-collector). The old S3 latest.json modal is only a
    | fallback when NATIVEPHP_UPDATER_ENABLED=false.
    |
    | Installers upload with stable object names (overwrite in place):
    | Pulse-setup.exe / Pulse-arm64.dmg / Pulse-arm64.zip.
    | latest.json holds the semver. electron-updater also needs latest.yml /
    | latest-mac.yml in the same prefix. After each upload, every other object
    | under the S3 prefix is deleted so only the latest installer set remains.
    |
    */
    'enabled' => (bool) env('DESKTOP_INSTALLER_UPDATE_ENABLED', true),

    'disk' => 'backup-s3',

    's3_prefix' => env('DESKTOP_INSTALLER_S3_PREFIX', 'biometric_installer'),

    /** How often (minutes) to re-query S3. 0 = every desktop request (recommended). */
    'check_interval_minutes' => (int) env('DESKTOP_INSTALLER_CHECK_INTERVAL_MINUTES', 0),

    /** Pre-signed download URL lifetime (minutes). */
    'download_url_minutes' => (int) env('DESKTOP_INSTALLER_DOWNLOAD_URL_MINUTES', 60),

    /** Filename prefix for S3 installer objects. */
    'installer_basename' => env('NATIVEPHP_INSTALLER_BASENAME', 'Pulse'),

    /** When a downloaded update is ready, quit and install immediately (Skolaris Desktop behavior). */
    'force_install' => (bool) env('NATIVEPHP_FORCE_UPDATE', true),

    /**
     * Local dist/ filename patterns. Capturing group 1 = semver version.
     * electron-builder uses APP_NAME ("Pulse") in artifact names.
     * Accepts Pulse-* (current) and Biometric Collector-* (legacy local artifacts).
     */
    'artifacts' => [
        'win-x64' => '/^(?:Pulse|Biometric Collector)-(.+)-setup\\.exe$/i',
        'mac-arm64' => '/^(?:Pulse|Biometric Collector)-(.+)-arm64\\.dmg$/i',
        'mac-arm64-zip' => '/^(?:Pulse|Biometric Collector)-(.+)-arm64\\.zip$/i',
        'mac-x64' => '/^(?:Pulse|Biometric Collector)-(.+)-x64\\.dmg$/i',
        'mac-x64-zip' => '/^(?:Pulse|Biometric Collector)-(.+)-x64\\.zip$/i',
    ],
];
