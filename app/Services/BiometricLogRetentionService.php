<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use Carbon\CarbonImmutable;
use DateTimeInterface;
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
        $cutoffLocal = $this->wallClockCutoff($months);

        if (config('biometric.timestamps_stored_as_utc', true)) {
            return $cutoffLocal->utc();
        }

        return $cutoffLocal;
    }

    /**
     * Device clocks are naive wall time (no timezone). Compare punches against this.
     */
    public function wallClockCutoff(int $months): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'Asia/Manila');

        return CarbonImmutable::now($timezone)->subMonthsNoOverflow($months)->startOfDay();
    }

    public function isBeforeWallClockCutoff(mixed $punchedAt, CarbonImmutable $wallCutoff): bool
    {
        $stamp = $this->wallClockStamp($punchedAt);

        if ($stamp === null) {
            return false;
        }

        return $stamp < $wallCutoff->format('Y-m-d H:i:s');
    }

    /**
     * Device attendance is cleared on the last day of the month.
     * If that day was missed, the next collect catches up once.
     */
    public function deviceAttendanceClearIsDue(?string $clearedThroughMonth, ?CarbonImmutable $today = null): bool
    {
        $today = $this->today($today);

        if ($clearedThroughMonth === null || ! preg_match('/^\d{4}-\d{2}$/', $clearedThroughMonth)) {
            return $today->isSameDay($today->endOfMonth());
        }

        $timezone = $this->timezone();
        $nextDue = CarbonImmutable::parse($clearedThroughMonth.'-01', $timezone)
            ->endOfMonth()
            ->addDay()
            ->endOfMonth()
            ->startOfDay();

        return $today->greaterThanOrEqualTo($nextDue);
    }

    /**
     * Month label stored after a successful end-of-month clear (YYYY-MM).
     */
    public function deviceAttendanceClearMonth(?CarbonImmutable $today = null): string
    {
        $today = $this->today($today);

        if ($today->isSameDay($today->endOfMonth())) {
            return $today->format('Y-m');
        }

        return $today->subMonthNoOverflow()->format('Y-m');
    }

    /**
     * ZKTeco can only erase the entire attendance log. Write a verified JSON backup
     * of every punch that clear() will remove, then the caller may clear the device.
     *
     * @param  list<array{
     *     device_uid: ?int,
     *     user_id: string,
     *     punched_at: mixed,
     *     verify_mode: ?string,
     *     punch_state: ?string,
     * }>  $records
     * @param  array<string, array{name: ?string, card_number: ?string}>  $userMap
     * @return list<string>
     */
    public function archiveDeviceAttendanceBeforeClear(
        BiometricDevice $device,
        array $records,
        int $months,
        CarbonImmutable $wallCutoff,
        array $userMap = [],
    ): array {
        if ($records === []) {
            throw new \RuntimeException('Refusing to clear device attendance without a JSON backup.');
        }

        $device->loadMissing('campus');
        $chunkSize = max(50, (int) config('biometric.logs.retention.chunk_size', 500));
        $files = [];
        $part = 0;

        $expiredKeys = [];

        foreach ($records as $record) {
            if ($this->isBeforeWallClockCutoff($record['punched_at'] ?? null, $wallCutoff)) {
                $expiredKeys[$this->punchKey($record)] = true;
            }
        }

        if ($expiredKeys === []) {
            throw new \RuntimeException('Refusing to clear device attendance when no punch is past retention.');
        }

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            $part++;
            $files[] = $this->writeDeviceClearArchiveFile($device, $chunk, $months, $wallCutoff, $part, $userMap);
        }

        $this->assertBackupContainsExpiredPunches($files, $expiredKeys);

        return $files;
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
     * @param  list<array{
     *     device_uid: ?int,
     *     user_id: string,
     *     punched_at: mixed,
     *     verify_mode: ?string,
     *     punch_state: ?string,
     * }>  $records
     * @param  array<string, array{name: ?string, card_number: ?string}>  $userMap
     */
    private function writeDeviceClearArchiveFile(
        BiometricDevice $device,
        array $records,
        int $months,
        CarbonImmutable $wallCutoff,
        int $part,
        array $userMap,
    ): string {
        $directory = $this->archiveDirectory();
        $slug = $this->installation->slug();
        $stamp = now()->format('YmdHis');
        $filename = sprintf('%s_device_cleared_%s_part%03d.json', $slug, $stamp, $part);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $attempt = 0;
        while (File::exists($path) && $attempt < 30) {
            $attempt++;
            $filename = sprintf('%s_device_cleared_%s_part%03d.json', $slug, $stamp.'_'.$attempt, $part);
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
        }

        $logs = [];
        $expiredCount = 0;

        foreach ($records as $record) {
            $punchedAt = $this->wallClockStamp($record['punched_at'] ?? null);
            $pastRetention = $punchedAt !== null && $punchedAt < $wallCutoff->format('Y-m-d H:i:s');

            if ($pastRetention) {
                $expiredCount++;
            }

            $profile = $userMap[(string) ($record['user_id'] ?? '')] ?? null;

            $logs[] = [
                'campus_code' => $device->campus?->code,
                'device_id' => $device->id,
                'device_name' => $device->name,
                'device_model' => $device->model,
                'device_ip' => $device->ip_address,
                'device_uid' => $record['device_uid'] ?? null,
                'user_id' => (string) ($record['user_id'] ?? ''),
                'user_name' => $profile['name'] ?? null,
                'card_number' => $profile['card_number'] ?? null,
                'punched_at' => $punchedAt,
                'verify_mode' => $record['verify_mode'] ?? null,
                'punch_state' => $record['punch_state'] ?? null,
                'past_retention' => $pastRetention,
            ];
        }

        $payload = [
            'kind' => 'deleted_attendance',
            'reason' => 'device_retention_clear',
            'retention_months' => $months,
            'cutoff_at' => $wallCutoff->toIso8601String(),
            'archived_at' => now()->toIso8601String(),
            'collector_name' => $this->installation->displayName() ?? $slug,
            'biometric_name' => $slug,
            'device_id' => $device->id,
            'device_name' => $device->name,
            'device_ip' => $device->ip_address,
            'logs_count' => count($logs),
            'expired_count' => $expiredCount,
            'note' => 'Backup of attendance erased from the ZKTeco device. The device can only clear the full attendance log. Punches still inside the retention window remain in Pulse.',
            'logs' => $logs,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode device attendance backup as JSON. Device logs were not deleted.');
        }

        File::put($path, $json);
        $this->assertArchiveMatches($path, count($logs));

        return $filename;
    }

    private function assertArchiveMatches(string $path, int $expectedCount): void
    {
        if (! File::isFile($path) || File::size($path) === 0) {
            throw new \RuntimeException('Device attendance backup file is missing. Device logs were not deleted.');
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)
            || ($decoded['kind'] ?? null) !== 'deleted_attendance'
            || (int) ($decoded['logs_count'] ?? -1) !== $expectedCount
            || ! is_array($decoded['logs'] ?? null)
            || count($decoded['logs']) !== $expectedCount
        ) {
            File::delete($path);

            throw new \RuntimeException('Device attendance backup could not be verified. Device logs were not deleted.');
        }
    }

    /**
     * @param  list<string>  $filenames
     * @param  array<string, true>  $expiredKeys
     */
    private function assertBackupContainsExpiredPunches(array $filenames, array $expiredKeys): void
    {
        $directory = $this->archiveDirectoryPath();

        foreach ($filenames as $filename) {
            $decoded = json_decode((string) File::get($directory.DIRECTORY_SEPARATOR.$filename), true);
            $logs = is_array($decoded) ? ($decoded['logs'] ?? []) : [];

            if (! is_array($logs)) {
                continue;
            }

            foreach ($logs as $log) {
                if (! is_array($log) || empty($log['past_retention'])) {
                    continue;
                }

                $key = $this->punchKey($log);
                unset($expiredKeys[$key]);
            }
        }

        if ($expiredKeys !== []) {
            throw new \RuntimeException('JSON backup is missing punches that would be deleted. Device logs were not deleted.');
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function punchKey(array $record): string
    {
        return implode('|', [
            (string) ($record['user_id'] ?? ''),
            (string) ($this->wallClockStamp($record['punched_at'] ?? null) ?? ''),
            (string) ($record['device_uid'] ?? ''),
        ]);
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Manila');
    }

    private function today(?CarbonImmutable $today): CarbonImmutable
    {
        $timezone = $this->timezone();

        return ($today ?? CarbonImmutable::now($timezone))->timezone($timezone)->startOfDay();
    }

    private function wallClockStamp(mixed $punchedAt): ?string
    {
        if ($punchedAt instanceof DateTimeInterface) {
            return $punchedAt->format('Y-m-d H:i:s');
        }

        if (! is_string($punchedAt) || trim($punchedAt) === '') {
            return null;
        }

        return CarbonImmutable::parse($punchedAt)->format('Y-m-d H:i:s');
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
