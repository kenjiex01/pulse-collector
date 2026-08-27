<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BiometricLogS3Uploader
{
    public function __construct(
        private readonly CollectorInstallationService $installation,
    ) {}

    public function isConfigured(): bool
    {
        if (! (bool) config('biometric.s3.enabled', true)) {
            return false;
        }

        $bucket = trim((string) config('biometric.s3.bucket', ''));
        $key = trim((string) config('biometric.s3.key', ''));
        $secret = trim((string) config('biometric.s3.secret', ''));

        return $bucket !== '' && $key !== '' && $secret !== '';
    }

    public function upload(string $localPath, string $filename, ?string $biometricName = null): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Biometric S3 upload is not configured.');
        }

        if (! File::exists($localPath)) {
            throw new \RuntimeException('Export file is missing: '.$localPath);
        }

        $disk = (string) config('biometric.s3.disk', 'backup-s3');
        $key = $this->objectKey($filename, $biometricName);

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read export file: '.$localPath);
        }

        try {
            $uploaded = Storage::disk($disk)->put(
                $key,
                $stream,
                [
                    'visibility' => 'private',
                    'ContentType' => 'application/gzip',
                ],
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $uploaded) {
            throw new \RuntimeException('S3 upload returned false for '.$key);
        }

        return $key;
    }

    /**
     * biometric_logs/{YYYY}/{MM}/{biometric_name}/{biometric_name}_YYYYMMDDHHMMSS.json.gzip
     */
    public function objectKey(string $filename, ?string $biometricName = null): string
    {
        $prefix = trim((string) config('biometric.s3.prefix', 'biometric_logs'), '/');
        $name = $biometricName ?: $this->installation->slug();
        $name = $this->sanitizeSegment($name);

        $relative = sprintf(
            '%s/%s/%s/%s',
            now()->format('Y'),
            now()->format('m'),
            $name,
            $filename,
        );

        return $prefix === '' ? $relative : $prefix.'/'.$relative;
    }

    private function sanitizeSegment(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';
        $slug = trim($slug, '.-_');

        return $slug === '' ? 'desktop' : substr($slug, 0, 64);
    }
}
