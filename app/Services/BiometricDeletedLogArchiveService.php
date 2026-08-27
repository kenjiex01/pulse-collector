<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Campus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BiometricDeletedLogArchiveService
{
    public function __construct(
        private readonly BiometricLogRetentionService $retention,
    ) {}

    /**
     * @return list<array{
     *     filename: string,
     *     size_bytes: int,
     *     modified_at: string,
     *     logs_count: int|null,
     *     archived_at: string|null,
     *     collector_name: string|null,
     * }>
     */
    public function listArchives(): array
    {
        $directory = $this->retention->archiveDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        $files = [];

        foreach (File::files($directory) as $file) {
            $filename = $file->getFilename();

            if (! $this->isValidArchiveFilename($filename)) {
                continue;
            }

            $meta = $this->readArchiveMeta($file->getPathname());

            $files[] = [
                'filename' => $filename,
                'size_bytes' => $file->getSize(),
                'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                'logs_count' => $meta['logs_count'],
                'archived_at' => $meta['archived_at'],
                'collector_name' => $meta['collector_name'],
            ];
        }

        usort($files, fn (array $left, array $right) => strcmp($right['modified_at'], $left['modified_at']));

        return $files;
    }

    public function downloadResponse(string $filename): BinaryFileResponse
    {
        $path = $this->resolveArchivePath($filename);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @return array{
     *     inserted: int,
     *     skipped_duplicates: int,
     *     skipped_unmatched: int,
     *     stored_copy: bool,
     *     filename: string|null,
     * }
     */
    public function importUploadedJson(UploadedFile $file, bool $storeCopy = true): array
    {
        $raw = (string) file_get_contents($file->getRealPath() ?: '');

        if ($raw === '') {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw new RuntimeException('The uploaded file is not valid JSON.');
        }

        $result = $this->importPayload($payload);
        $storedFilename = null;

        if ($storeCopy) {
            $storedFilename = $this->storeCopy($file, $raw);
        }

        return [
            ...$result,
            'stored_copy' => $storedFilename !== null,
            'filename' => $storedFilename,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{inserted: int, skipped_duplicates: int, skipped_unmatched: int}
     */
    public function importPayload(array $payload): array
    {
        if (($payload['kind'] ?? null) !== 'deleted_attendance') {
            throw new RuntimeException('Only deleted attendance backup JSON files can be imported.');
        }

        $logs = $payload['logs'] ?? null;

        if (! is_array($logs) || $logs === []) {
            throw new RuntimeException('The backup JSON does not contain any log rows.');
        }

        $rows = [];
        $skippedUnmatched = 0;

        foreach ($logs as $log) {
            if (! is_array($log)) {
                $skippedUnmatched++;

                continue;
            }

            $mapped = $this->mapLogRow($log);

            if ($mapped === null) {
                $skippedUnmatched++;

                continue;
            }

            $rows[] = $mapped;
        }

        if ($rows === []) {
            throw new RuntimeException('No log rows could be matched to local campuses or devices.');
        }

        $inserted = 0;
        $chunkSize = max(10, (int) config('biometric.export.insert_chunk_size', 40));

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $before = BiometricAttendanceLog::query()->count();
            BiometricAttendanceLog::query()->insertOrIgnore($chunk);
            $after = BiometricAttendanceLog::query()->count();
            $inserted += max(0, $after - $before);
        }

        return [
            'inserted' => $inserted,
            'skipped_duplicates' => max(0, count($rows) - $inserted),
            'skipped_unmatched' => $skippedUnmatched,
        ];
    }

    public function resolveArchivePath(string $filename): string
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->retention->archiveDirectoryPath().DIRECTORY_SEPARATOR.$filename;

        if (! File::isFile($path)) {
            throw new RuntimeException('Backup file was not found.');
        }

        return $path;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        $filename = basename($filename);

        if ($filename === '' || str_contains($filename, '..') || ! $this->isValidArchiveFilename($filename)) {
            throw new RuntimeException('Invalid backup filename.');
        }

        return $filename;
    }

    private function isValidArchiveFilename(string $filename): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+\.json$/', $filename);
    }

    /**
     * @return array{logs_count: int|null, archived_at: string|null, collector_name: string|null}
     */
    private function readArchiveMeta(string $path): array
    {
        try {
            $payload = json_decode((string) File::get($path), true);
        } catch (\Throwable) {
            return [
                'logs_count' => null,
                'archived_at' => null,
                'collector_name' => null,
            ];
        }

        if (! is_array($payload)) {
            return [
                'logs_count' => null,
                'archived_at' => null,
                'collector_name' => null,
            ];
        }

        return [
            'logs_count' => isset($payload['logs_count']) ? (int) $payload['logs_count'] : null,
            'archived_at' => is_string($payload['archived_at'] ?? null) ? $payload['archived_at'] : null,
            'collector_name' => is_string($payload['collector_name'] ?? null) ? $payload['collector_name'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $log
     * @return array<string, mixed>|null
     */
    private function mapLogRow(array $log): ?array
    {
        $device = $this->resolveDevice($log);

        if ($device === null) {
            return null;
        }

        $userId = trim((string) ($log['user_id'] ?? ''));
        $punchedAt = trim((string) ($log['punched_at'] ?? ''));

        if ($userId === '' || $punchedAt === '') {
            return null;
        }

        return [
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'device_uid' => isset($log['device_uid']) ? (int) $log['device_uid'] : null,
            'user_id' => $userId,
            'user_name' => $this->nullableString($log['user_name'] ?? null),
            'card_number' => $this->nullableString($log['card_number'] ?? null),
            'punched_at' => $punchedAt,
            'verify_mode' => $this->nullableString($log['verify_mode'] ?? null),
            'punch_state' => $this->nullableString($log['punch_state'] ?? null),
            's3_object_key' => $this->nullableString($log['s3_object_key'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function resolveDevice(array $log): ?BiometricDevice
    {
        $deviceId = (int) ($log['device_id'] ?? 0);

        if ($deviceId > 0) {
            $byId = BiometricDevice::query()->find($deviceId);

            if ($byId !== null) {
                return $byId;
            }
        }

        $campusCode = strtoupper(trim((string) ($log['campus_code'] ?? '')));
        $deviceIp = trim((string) ($log['device_ip'] ?? ''));
        $deviceName = trim((string) ($log['device_name'] ?? ''));

        $campus = $campusCode !== ''
            ? Campus::query()->whereRaw('UPPER(code) = ?', [$campusCode])->first()
            : null;

        if ($campus !== null && $deviceIp !== '') {
            $byIp = BiometricDevice::query()
                ->where('campus_id', $campus->id)
                ->where('ip_address', $deviceIp)
                ->first();

            if ($byIp !== null) {
                return $byIp;
            }
        }

        if ($campus !== null && $deviceName !== '') {
            return BiometricDevice::query()
                ->where('campus_id', $campus->id)
                ->where('name', $deviceName)
                ->first();
        }

        return null;
    }

    private function storeCopy(UploadedFile $file, string $raw): ?string
    {
        $original = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $original = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $original) ?: 'uploaded-backup.json';

        if (! str_ends_with(strtolower($original), '.json')) {
            $original .= '.json';
        }

        $directory = $this->retention->archiveDirectory();
        $target = $directory.DIRECTORY_SEPARATOR.$original;

        if (File::exists($target)) {
            $stamp = now()->format('YmdHis');
            $target = $directory.DIRECTORY_SEPARATOR.pathinfo($original, PATHINFO_FILENAME).'_'.$stamp.'.json';
        }

        File::put($target, $raw);

        return basename($target);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
