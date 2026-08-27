<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BiometricLogRetentionService
{
    public function __construct(
        private readonly CollectorInstallationService $installation,
    ) {}

    public function archiveDirectory(): string
    {
        $directory = $this->archiveDirectoryPath();
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    public function archiveDirectoryPath(): string
    {
        return storage_path('app/'.$this->archiveDirectoryName());
    }

    public function archiveDirectoryName(): string
    {
        return trim((string) config('biometric.logs.retention.archive_dir', 'biometric-deleted-logs'), '/');
    }

    /**
     * Archive logs older than the retention window to local JSON, then delete them.
     *
     * @return array{pruned: int, files: list<string>, cutoff_at: ?string, skipped: bool}
     */
    public function pruneExpiredLogs(): array
    {
        $months = $this->installation->logRetentionMonths();

        if ($months === null) {
            return [
                'pruned' => 0,
                'files' => [],
                'cutoff_at' => null,
                'skipped' => true,
            ];
        }

        $cutoff = $this->cutoffAt($months);
        $chunkSize = max(50, (int) config('biometric.logs.retention.chunk_size', 500));
        $deleteChunkSize = max(10, (int) config('biometric.export.sqlite_update_chunk_size', 100));

        $pruned = 0;
        $files = [];
        $part = 0;

        while (true) {
            /** @var Collection<int, BiometricAttendanceLog> $logs */
            $logs = BiometricAttendanceLog::query()
                ->with(['campus', 'device'])
                ->where('punched_at', '<', $cutoff->format('Y-m-d H:i:s'))
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            if ($logs->isEmpty()) {
                break;
            }

            $part++;
            $files[] = $this->writeArchiveFile($logs, $months, $cutoff, $part);
            $deleted = $this->deleteLogs($logs, $deleteChunkSize);

            if ($deleted === 0) {
                throw new \RuntimeException('Archived expired logs to JSON but could not delete them from SQLite.');
            }

            $pruned += $deleted;
        }

        if ($pruned > 0) {
            Log::info('Biometric log retention pruned expired punches.', [
                'months' => $months,
                'cutoff_at' => $cutoff->toIso8601String(),
                'pruned' => $pruned,
                'files' => $files,
            ]);
        }

        return [
            'pruned' => $pruned,
            'files' => $files,
            'cutoff_at' => $cutoff->toDateString(),
            'skipped' => false,
        ];
    }

    public function cutoffAt(int $months): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Manila');
        $cutoffLocal = CarbonImmutable::now($timezone)->subMonthsNoOverflow($months)->startOfDay();

        if (config('biometric.timestamps_stored_as_utc', true)) {
            return $cutoffLocal->utc();
        }

        return $cutoffLocal;
    }

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     */
    private function writeArchiveFile(
        Collection $logs,
        int $months,
        CarbonImmutable $cutoff,
        int $part,
    ): string {
        $directory = $this->archiveDirectory();
        $slug = $this->installation->slug();
        $stamp = now()->format('YmdHis');
        $filename = sprintf('%s_deleted_%s_part%03d.json', $slug, $stamp, $part);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $attempt = 0;
        while (File::exists($path) && $attempt < 30) {
            $attempt++;
            $filename = sprintf('%s_deleted_%s_part%03d.json', $slug, $stamp.'_'.$attempt, $part);
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
        }

        $payload = [
            'kind' => 'deleted_attendance',
            'reason' => 'retention',
            'retention_months' => $months,
            'cutoff_at' => $cutoff->toIso8601String(),
            'archived_at' => now()->toIso8601String(),
            'collector_name' => $this->installation->displayName() ?? $slug,
            'biometric_name' => $slug,
            'logs_count' => $logs->count(),
            'logs' => $logs->map(fn (BiometricAttendanceLog $log) => [
                'id' => $log->id,
                'campus_code' => $log->campus?->code,
                'device_id' => $log->biometric_device_id,
                'device_name' => $log->device?->name,
                'device_model' => $log->device?->model,
                'device_ip' => $log->device?->ip_address,
                'device_uid' => $log->device_uid,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'card_number' => $log->card_number,
                'punched_at' => $log->punched_at?->format('Y-m-d H:i:s'),
                'verify_mode' => $log->verify_mode,
                'punch_state' => $log->punch_state,
                's3_object_key' => $log->s3_object_key,
            ])->values()->all(),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode deleted biometric logs as JSON.');
        }

        File::put($path, $json);

        return $filename;
    }

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     */
    private function deleteLogs(Collection $logs, int $deleteChunkSize): int
    {
        $ids = $logs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $deleted = 0;

        foreach (array_chunk($ids, $deleteChunkSize) as $chunk) {
            $deleted += BiometricAttendanceLog::query()->whereIn('id', $chunk)->delete();
        }

        return $deleted;
    }
}
