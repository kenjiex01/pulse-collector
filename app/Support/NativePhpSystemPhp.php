<?php

namespace App\Support;

class NativePhpSystemPhp
{
    public static function available(): ?string
    {
        foreach (self::candidates() as $path) {
            if ($path === '' || ! is_executable($path)) {
                continue;
            }

            if (str_contains($path, 'nativephp/electron/resources/php')) {
                continue;
            }

            return $path;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidates(): array
    {
        $fromPath = trim((string) shell_exec('command -v php 2>/dev/null'));

        return array_values(array_unique(array_filter([
            '/opt/homebrew/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            $fromPath !== '' ? $fromPath : null,
        ])));
    }

    /**
     * @return array<string, string>
     */
    public static function environment(): array
    {
        $vars = [
            'APP_PATH' => base_path(),
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_DATABASE_PATH' => (string) config('nativephp-internal.database_path', ''),
            'NATIVEPHP_STORAGE_PATH' => (string) config('nativephp-internal.storage_path', ''),
            'LARAVEL_STORAGE_PATH' => (string) config('nativephp-internal.storage_path', ''),
        ];

        return array_filter($vars, fn (string $value): bool => $value !== '');
    }

    public static function shouldUseForDeviceIo(): bool
    {
        return (bool) config('nativephp-internal.running')
            && PHP_OS_FAMILY === 'Darwin'
            && self::available() !== null;
    }
}
