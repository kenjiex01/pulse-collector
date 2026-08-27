<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\LogExportBatch;
use App\Support\NativePhpSystemPhp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class BiometricLogCollector
{
    public function __construct(
        private readonly ZkTecoDeviceReader $reader,
        private readonly BiometricDeviceUserSyncService $userSync,
        private readonly BiometricLogJsonExporter $exporter,
        private readonly BiometricLogS3Uploader $uploader,
        private readonly CollectorInstallationService $installation,
    ) {}

    /**
     * @return array{
     *     devices_processed: int,
     *     logs_inserted: int,
     *     batches_uploaded: int,
     *     errors: list<string>,
     * }
     */
    public function run(): array
    {
        $this->prepareRuntime();
        $this->ensureRetentionIsConfigured();

        if (
            NativePhpSystemPhp::shouldUseForDeviceIo()
            && ! getenv('BIOMETRIC_COLLECT_IN_PROCESS')
        ) {
            return $this->runViaSystemPhp();
        }

        $summary = [
            'devices_processed' => 0,
            'logs_inserted' => 0,
            'batches_uploaded' => 0,
            'errors' => [],
        ];

        $devices = BiometricDevice::query()
            ->with('campus')
            ->where('is_active', true)
            ->whereHas('campus', fn ($query) => $query->where('is_active', true))
            ->orderBy('campus_id')
            ->orderBy('id')
            ->get();

        foreach ($devices as $device) {
            $summary['devices_processed']++;

            try {
                $result = $this->collectFromDevice($device);
                $summary['logs_inserted'] += $result['logs_inserted'];

                if ($this->exportAndUploadAttendanceForDevice($device)) {
                    $summary['batches_uploaded']++;
                }

                if ($this->exportAndUploadPendingUsersForDevice($device)) {
                    $summary['batches_uploaded']++;
                }

                $device->forceFill([
                    'last_collected_at' => now(),
                    'last_error' => null,
                ])->save();
            } catch (Throwable $exception) {
                $message = sprintf(
                    'Campus %s / device %s: %s',
                    $device->campus?->code ?? '?',
                    $device->name,
                    $exception->getMessage(),
                );
                $summary['errors'][] = $message;

                $device->forceFill([
                    'last_error' => $exception->getMessage(),
                ])->save();

                Log::error('Biometric collection failed for device.', [
                    'device_id' => $device->id,
                    'message' => $exception->getMessage(),
                ]);

                report($exception);
            }
        }

        try {
            app(BiometricLogRetentionService::class)->pruneExpiredLogs();
        } catch (Throwable $exception) {
            Log::error('Biometric log retention failed after collect.', [
                'message' => $exception->getMessage(),
            ]);
            $summary['errors'][] = 'Log retention: '.$exception->getMessage();
        }

        return $summary;
    }

    /**
     * @return array{
     *     devices_processed: int,
     *     logs_inserted: int,
     *     batches_uploaded: int,
     *     errors: list<string>,
     * }
     */
    private function runViaSystemPhp(): array
    {
        $php = NativePhpSystemPhp::available();
        if ($php === null) {
            return $this->runInProcessIgnoringOutOfProcess();
        }

        $env = NativePhpSystemPhp::environment();
        $env['BIOMETRIC_COLLECT_IN_PROCESS'] = '1';

        $result = Process::path(base_path())
            ->timeout(600)
            ->env($env)
            ->run([$php, base_path('artisan'), 'biometric:collect', '--json']);

        if (! $result->successful()) {
            $message = trim($result->errorOutput()) ?: trim($result->output()) ?: 'System PHP collect failed.';

            return [
                'devices_processed' => 0,
                'logs_inserted' => 0,
                'batches_uploaded' => 0,
                'errors' => [$message],
            ];
        }

        try {
            $decoded = json_decode(trim($result->output()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [
                'devices_processed' => 0,
                'logs_inserted' => 0,
                'batches_uploaded' => 0,
                'errors' => ['Could not parse collect output: '.$exception->getMessage()],
            ];
        }

        return is_array($decoded) ? $decoded : [
            'devices_processed' => 0,
            'logs_inserted' => 0,
            'batches_uploaded' => 0,
            'errors' => ['Invalid collect summary from system PHP.'],
        ];
    }

    /**
     * @return array{
     *     devices_processed: int,
     *     logs_inserted: int,
     *     batches_uploaded: int,
     *     errors: list<string>,
     * }
     */
    private function runInProcessIgnoringOutOfProcess(): array
    {
        putenv('BIOMETRIC_COLLECT_IN_PROCESS=1');

        return $this->run();
    }

    /**
     * @return array{logs_inserted: int}
     */
    private function collectFromDevice(BiometricDevice $device): array
    {
        $snapshot = $this->reader->fetchDeviceSnapshot($device);
        $snapshot['attendance'] = $this->filterAttendanceByStartDate($snapshot['attendance']);

        $userMap = $this->userSync->sync($device, $snapshot['users']);

        $inserted = $this->insertAttendanceRecords($device, $snapshot['attendance'], $userMap);

        return ['logs_inserted' => $inserted];
    }

    /**
     * @param  list<array{
     *     device_uid: ?int,
     *     user_id: string,
     *     punched_at: mixed,
     *     verify_mode: ?string,
     *     punch_state: ?string,
     * }>  $records
     * @return list<array{
     *     device_uid: ?int,
     *     user_id: string,
     *     punched_at: mixed,
     *     verify_mode: ?string,
     *     punch_state: ?string,
     * }>
     */
    private function filterAttendanceByStartDate(array $records): array
    {
        $startDate = $this->installation->attendanceStartDate();

        if ($startDate === null) {
            return $records;
        }

        return array_values(array_filter($records, function (array $record) use ($startDate): bool {
            $punchedAt = $record['punched_at'] ?? null;

            if ($punchedAt instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($punchedAt) >= $startDate;
            }

            if (! is_string($punchedAt) || trim($punchedAt) === '') {
                return false;
            }

            return CarbonImmutable::parse($punchedAt) >= $startDate;
        }));
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
    private function insertAttendanceRecords(BiometricDevice $device, array $records, array $userMap): int
    {
        if ($records === []) {
            return 0;
        }

        $inserted = 0;
        $now = now();
        $chunkSize = max(10, (int) config('biometric.export.insert_chunk_size', 40));

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            $rows = [];

            foreach ($chunk as $record) {
                $profile = $userMap[$record['user_id']] ?? null;
                $punchedAt = $record['punched_at'];
                $punchedAtString = $punchedAt instanceof \DateTimeInterface
                    ? $punchedAt->format('Y-m-d H:i:s')
                    : (string) $punchedAt;

                $rows[] = [
                    'campus_id' => $device->campus_id,
                    'biometric_device_id' => $device->id,
                    'device_uid' => $record['device_uid'],
                    'user_id' => $record['user_id'],
                    'user_name' => $profile['name'] ?? null,
                    'card_number' => $profile['card_number'] ?? null,
                    'punched_at' => $punchedAtString,
                    'verify_mode' => $record['verify_mode'],
                    'punch_state' => $record['punch_state'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $inserted += (int) BiometricAttendanceLog::query()->insertOrIgnore($rows);
        }

        return max(0, $inserted);
    }

    private function exportAndUploadAttendanceForDevice(BiometricDevice $device): bool
    {
        $uploadedAny = false;
        $chunkSize = max(100, (int) config('biometric.export.s3_chunk_size', 500));

        BiometricAttendanceLog::query()
            ->where('biometric_device_id', $device->id)
            ->pendingS3Push()
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $logs) use ($device, &$uploadedAny): void {
                if ($this->exportAndUploadAttendanceBatch($device, $logs)) {
                    $uploadedAny = true;
                }
            });

        return $uploadedAny;
    }

    private function exportAndUploadPendingUsersForDevice(BiometricDevice $device): bool
    {
        $uploadedAny = false;
        $chunkSize = max(100, (int) config('biometric.export.s3_chunk_size', 500));

        BiometricDeviceUser::query()
            ->where('biometric_device_id', $device->id)
            ->pendingS3Push()
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $users) use ($device, &$uploadedAny): void {
                if ($this->exportAndUploadUsersBatch($device, $users)) {
                    $uploadedAny = true;
                }
            });

        return $uploadedAny;
    }

    private function prepareRuntime(): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
        ignore_user_abort(true);
    }

    private function ensureRetentionIsConfigured(): void
    {
        if ($this->installation->logRetentionMonths() !== null) {
            return;
        }

        throw new \RuntimeException(
            'Set Months to keep first before collecting logs. This prevents the app from keeping or deleting your full history by mistake.',
        );
    }

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     */
    private function exportAndUploadAttendanceBatch(BiometricDevice $device, Collection $logs): bool
    {
        $batch = LogExportBatch::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'batch_kind' => 'attendance',
            'logs_count' => $logs->count(),
            'users_count' => 0,
            'sql_filename' => '',
        ]);

        try {
            $export = $this->exporter->writeAttendanceBatch($batch, $logs, $device);
            $batch->forceFill(['sql_filename' => $export['filename']])->save();

            $uploaded = $this->finalizeUpload(
                $batch,
                $export['path'],
                $export['filename'],
                $export['biometric_name'],
            );

            if ($uploaded) {
                $pushedAt = now();
                $this->markAttendanceLogsAsPushed($logs, $batch->id, $pushedAt, $batch->s3_key);
            }

            return $uploaded;
        } catch (Throwable $exception) {
            $batch->forceFill([
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, BiometricDeviceUser>  $users
     */
    private function exportAndUploadUsersBatch(BiometricDevice $device, Collection $users): bool
    {
        $batch = LogExportBatch::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'batch_kind' => 'users',
            'logs_count' => 0,
            'users_count' => $users->count(),
            'sql_filename' => '',
        ]);

        try {
            $export = $this->exporter->writeUsersBatch($batch, $users, $device);
            $batch->forceFill(['sql_filename' => $export['filename']])->save();

            $uploaded = $this->finalizeUpload(
                $batch,
                $export['path'],
                $export['filename'],
                $export['biometric_name'],
            );

            if ($uploaded) {
                $pushedAt = now();
                $this->markDeviceUsersAsPushed($users, $batch->id, $pushedAt, $batch->s3_key);
            }

            return $uploaded;
        } catch (Throwable $exception) {
            $batch->forceFill([
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function finalizeUpload(
        LogExportBatch $batch,
        string $localPath,
        string $filename,
        string $biometricName,
    ): bool {
        if (! $this->uploader->isConfigured()) {
            $batch->forceFill([
                'error_message' => 'S3 is not configured; gzipped JSON kept locally only.',
            ])->save();

            return false;
        }

        $s3Key = $this->uploader->upload($localPath, $filename, $biometricName);

        $batch->forceFill([
            's3_key' => $s3Key,
            'uploaded_at' => now(),
            'error_message' => null,
        ])->save();

        if (! config('biometric.export.keep_local_sql', false)) {
            @unlink($localPath);
        }

        return true;
    }

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     */
    private function markAttendanceLogsAsPushed(
        Collection $logs,
        int $batchId,
        \DateTimeInterface $pushedAt,
        ?string $s3Key,
    ): void {
        foreach ($logs->pluck('id')->chunk((int) config('biometric.export.sqlite_update_chunk_size', 100)) as $ids) {
            BiometricAttendanceLog::query()
                ->whereIn('id', $ids->all())
                ->update([
                    'log_export_batch_id' => $batchId,
                    's3_pushed_at' => $pushedAt,
                    's3_object_key' => $s3Key,
                ]);
        }
    }

    /**
     * @param  Collection<int, BiometricDeviceUser>  $users
     */
    private function markDeviceUsersAsPushed(
        Collection $users,
        int $batchId,
        \DateTimeInterface $pushedAt,
        ?string $s3Key,
    ): void {
        foreach ($users->pluck('id')->chunk((int) config('biometric.export.sqlite_update_chunk_size', 100)) as $ids) {
            BiometricDeviceUser::query()
                ->whereIn('id', $ids->all())
                ->update([
                    's3_export_batch_id' => $batchId,
                    's3_pushed_at' => $pushedAt,
                    's3_object_key' => $s3Key,
                ]);
        }
    }
}
