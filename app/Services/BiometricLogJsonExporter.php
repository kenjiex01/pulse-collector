<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\LogExportBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class BiometricLogJsonExporter
{
    public function __construct(
        private readonly CollectorInstallationService $installation,
    ) {}

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     * @return array{path: string, filename: string, biometric_name: string}
     */
    public function writeAttendanceBatch(LogExportBatch $batch, Collection $logs, BiometricDevice $device): array
    {
        $biometricName = $this->biometricName();
        $filename = $this->buildFilename($biometricName);
        $path = $this->stagingPath($filename);

        $payload = [
            'kind' => 'attendance',
            'collector_name' => $this->installation->displayName() ?? $biometricName,
            'biometric_name' => $biometricName,
            'campus_code' => $device->campus?->code,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'model' => $device->model,
                'ip_address' => $device->ip_address,
            ],
            'batch_id' => $batch->id,
            'exported_at' => now()->toIso8601String(),
            'logs_count' => $logs->count(),
            'logs' => $logs->map(fn (BiometricAttendanceLog $log) => [
                'id' => $log->id,
                'device_uid' => $log->device_uid,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'card_number' => $log->card_number,
                'punched_at' => $log->punched_at?->format('Y-m-d H:i:s'),
                'verify_mode' => $log->verify_mode,
                'punch_state' => $log->punch_state,
            ])->values()->all(),
        ];

        $this->writeGzipJson($path, $payload);

        return [
            'path' => $path,
            'filename' => $filename,
            'biometric_name' => $biometricName,
        ];
    }

    /**
     * @param  Collection<int, BiometricDeviceUser>  $users
     * @return array{path: string, filename: string, biometric_name: string}
     */
    public function writeUsersBatch(LogExportBatch $batch, Collection $users, BiometricDevice $device): array
    {
        $biometricName = $this->biometricName();
        $filename = $this->buildFilename($biometricName);
        $path = $this->stagingPath($filename);

        $payload = [
            'kind' => 'users',
            'collector_name' => $this->installation->displayName() ?? $biometricName,
            'biometric_name' => $biometricName,
            'campus_code' => $device->campus?->code,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'model' => $device->model,
                'ip_address' => $device->ip_address,
            ],
            'batch_id' => $batch->id,
            'exported_at' => now()->toIso8601String(),
            'users_count' => $users->count(),
            'users' => $users->map(fn (BiometricDeviceUser $user) => [
                'id' => $user->id,
                'device_uid' => $user->device_uid,
                'user_id' => $user->user_id,
                'name' => $user->name,
                'card_number' => $user->card_number,
                'synced_at' => $user->synced_at?->format('Y-m-d H:i:s'),
            ])->values()->all(),
        ];

        $this->writeGzipJson($path, $payload);

        return [
            'path' => $path,
            'filename' => $filename,
            'biometric_name' => $biometricName,
        ];
    }

    public function biometricName(): string
    {
        return $this->installation->slug();
    }

    /**
     * {biometric_name}_YYYYMMDDHHMMSS.json.gzip
     */
    private function buildFilename(string $biometricName): string
    {
        $stamp = now()->format('YmdHis');
        $filename = sprintf('%s_%s.json.gzip', $biometricName, $stamp);
        $path = $this->stagingPath($filename);

        // Avoid local collisions when multiple batches export in the same second.
        $attempt = 0;
        while (File::exists($path) && $attempt < 30) {
            $attempt++;
            $stamp = now()->copy()->addSeconds($attempt)->format('YmdHis');
            $filename = sprintf('%s_%s.json.gzip', $biometricName, $stamp);
            $path = $this->stagingPath($filename);
        }

        return $filename;
    }

    private function stagingPath(string $filename): string
    {
        $directory = storage_path('app/'.trim((string) config('biometric.export.local_staging_dir', 'biometric-exports'), '/'));
        File::ensureDirectoryExists($directory);

        return $directory.'/'.$filename;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeGzipJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode biometric export JSON.');
        }

        $gzip = gzencode($json, 6);

        if ($gzip === false) {
            throw new \RuntimeException('Failed to gzip biometric export JSON.');
        }

        File::put($path, $gzip);
    }
}
