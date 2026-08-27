<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\LogExportBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class BiometricLogSqlExporter
{
    public function __construct(
        private readonly CollectorInstallationService $installation,
    ) {}

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $logs
     * @return array{path: string, filename: string}
     */
    public function writeAttendanceBatch(LogExportBatch $batch, Collection $logs, BiometricDevice $device): array
    {
        $table = (string) config('biometric.export.table_name', 'biometric_attendance_logs');
        $filename = $this->buildFilename($device, $batch, 'logs');
        $path = $this->stagingPath($filename);
        $collectorLabel = $this->installation->displayName() ?? $this->installation->slug();
        $campusCode = $device->campus?->code ?? 'campus';

        $lines = $this->fileHeader($collectorLabel, $campusCode, $device, 'Attendance log export');
        $lines[] = 'BEGIN TRANSACTION;';

        foreach ($logs as $log) {
            $lines[] = $this->attendanceInsertStatement($table, $log, $device);
        }

        $lines[] = 'COMMIT;';
        $lines[] = '';

        File::put($path, implode(PHP_EOL, $lines));

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * @param  Collection<int, BiometricDeviceUser>  $users
     * @return array{path: string, filename: string}
     */
    public function writeUsersBatch(LogExportBatch $batch, Collection $users, BiometricDevice $device): array
    {
        $table = (string) config('biometric.export.users_table_name', 'biometric_device_users');
        $filename = $this->buildFilename($device, $batch, 'users');
        $path = $this->stagingPath($filename);
        $collectorLabel = $this->installation->displayName() ?? $this->installation->slug();
        $campusCode = $device->campus?->code ?? 'campus';

        $lines = $this->fileHeader($collectorLabel, $campusCode, $device, 'Device user export (name + card/RFID)');
        $lines[] = 'BEGIN TRANSACTION;';

        foreach ($users as $user) {
            $lines[] = $this->userInsertStatement($table, $user, $device);
        }

        $lines[] = 'COMMIT;';
        $lines[] = '';

        File::put($path, implode(PHP_EOL, $lines));

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * @return list<string>
     */
    private function fileHeader(string $collectorLabel, string $campusCode, BiometricDevice $device, string $kind): array
    {
        return [
            '-- Biometric collector export',
            '-- Kind: '.$kind,
            '-- Collector: '.$collectorLabel,
            '-- Campus: '.$campusCode,
            '-- Device: '.$device->name.' ('.$device->ip_address.')',
            '-- Generated at: '.now()->toIso8601String(),
        ];
    }

    private function buildFilename(BiometricDevice $device, LogExportBatch $batch, string $suffix): string
    {
        $collectorSlug = $this->installation->slug();
        $campusCode = $device->campus?->code ?? 'campus';
        $timestamp = now()->format('Y-m-d_His');

        return sprintf(
            'biometric-%s-%s-%s-device-%d-%s-batch-%d-%s.sql',
            $collectorSlug,
            $campusCode,
            preg_replace('/[^A-Za-z0-9._-]+/', '-', $device->name) ?: 'device',
            $device->id,
            $suffix,
            $batch->id,
            $timestamp,
        );
    }

    private function stagingPath(string $filename): string
    {
        $directory = storage_path('app/'.trim((string) config('biometric.export.local_staging_dir', 'biometric-exports'), '/'));
        File::ensureDirectoryExists($directory);

        return $directory.'/'.$filename;
    }

    private function attendanceInsertStatement(string $table, BiometricAttendanceLog $log, BiometricDevice $device): string
    {
        $userName = $log->user_name;
        $cardNumber = $log->card_number;

        $columns = [
            'collector_name',
            'campus_code',
            'device_id',
            'device_name',
            'device_model',
            'device_ip',
            'device_uid',
            'user_id',
            'user_name',
            'card_number',
            'punched_at',
            'verify_mode',
            'punch_state',
            'source_log_id',
            'collected_at',
        ];

        $values = [
            $this->quote($this->installation->displayName() ?? $this->installation->slug()),
            $this->quote($device->campus?->code),
            (string) $device->id,
            $this->quote($device->name),
            $this->quote($device->model),
            $this->quote($device->ip_address),
            $log->device_uid === null ? 'NULL' : (string) $log->device_uid,
            $this->quote($log->user_id),
            $this->quote($userName),
            $this->quote($cardNumber),
            $this->quote($log->punched_at?->format('Y-m-d H:i:s')),
            $this->quote($log->verify_mode),
            $this->quote($log->punch_state),
            (string) $log->id,
            $this->quote(now()->format('Y-m-d H:i:s')),
        ];

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s);',
            $table,
            implode(', ', $columns),
            implode(', ', $values),
        );
    }

    private function userInsertStatement(string $table, BiometricDeviceUser $user, BiometricDevice $device): string
    {
        $columns = [
            'collector_name',
            'campus_code',
            'device_id',
            'device_name',
            'device_model',
            'device_ip',
            'source_user_id',
            'device_uid',
            'user_id',
            'user_name',
            'card_number',
            'synced_at',
            'exported_at',
        ];

        $values = [
            $this->quote($this->installation->displayName() ?? $this->installation->slug()),
            $this->quote($device->campus?->code),
            (string) $device->id,
            $this->quote($device->name),
            $this->quote($device->model),
            $this->quote($device->ip_address),
            (string) $user->id,
            $user->device_uid === null ? 'NULL' : (string) $user->device_uid,
            $this->quote($user->user_id),
            $this->quote($user->name),
            $this->quote($user->card_number),
            $this->quote($user->synced_at?->format('Y-m-d H:i:s')),
            $this->quote(now()->format('Y-m-d H:i:s')),
        ];

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s);',
            $table,
            implode(', ', $columns),
            implode(', ', $values),
        );
    }

    private function quote(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace("'", "''", $value)."'";
    }
}
