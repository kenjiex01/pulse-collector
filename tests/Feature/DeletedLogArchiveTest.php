<?php

namespace Tests\Feature;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Campus;
use App\Models\CollectorInstallation;
use App\Services\BiometricLogRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeletedLogArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $directory = storage_path('app/biometric-deleted-logs');
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_archives_index_lists_backup_files(): void
    {
        [$device] = $this->makeDevice();
        $this->makeLog($device, '2026-01-01 08:00:00');
        CollectorInstallation::current()->update(['log_retention_months' => 1]);

        app(BiometricLogRetentionService::class)->pruneExpiredLogs();

        $response = $this->get(route('archives.index'));

        $response->assertOk();
        $response->assertSee('_deleted_');
        $response->assertSee('Download');
    }

    public function test_archives_download_returns_json_file(): void
    {
        [$device] = $this->makeDevice();
        $this->makeLog($device, '2026-01-01 08:00:00');
        CollectorInstallation::current()->update(['log_retention_months' => 1]);

        $summary = app(BiometricLogRetentionService::class)->pruneExpiredLogs();
        $filename = $summary['files'][0];

        $response = $this->get(route('archives.download', ['filename' => $filename]));

        $response->assertOk();
        $response->assertDownload($filename);
    }

    public function test_upload_restores_deleted_logs_and_skips_duplicates(): void
    {
        [$device] = $this->makeDevice();
        $this->makeLog($device, '2026-01-01 08:00:00', '1001');
        CollectorInstallation::current()->update(['log_retention_months' => 1]);

        $summary = app(BiometricLogRetentionService::class)->pruneExpiredLogs();
        $filename = $summary['files'][0];
        $path = storage_path('app/biometric-deleted-logs/'.$filename);
        $this->assertFileExists($path);
        $this->assertSame(0, BiometricAttendanceLog::query()->count());

        $upload = UploadedFile::fake()->createWithContent('restore.json', (string) File::get($path));

        $first = $this->post(route('archives.upload.store'), [
            'backup_json' => $upload,
        ]);

        $first->assertRedirect(route('archives.upload'));
        $first->assertSessionHas('success');
        $this->assertSame(1, BiometricAttendanceLog::query()->count());

        $secondUpload = UploadedFile::fake()->createWithContent('restore-again.json', (string) File::get($path));

        $second = $this->post(route('archives.upload.store'), [
            'backup_json' => $secondUpload,
        ]);

        $second->assertRedirect(route('archives.upload'));
        $second->assertSessionHas('success');
        $this->assertSame(1, BiometricAttendanceLog::query()->count());
    }

    public function test_upload_rejects_non_deleted_attendance_json(): void
    {
        $payload = json_encode([
            'kind' => 'attendance',
            'logs' => [],
        ]);

        $upload = UploadedFile::fake()->createWithContent('bad.json', (string) $payload);

        $response = $this->post(route('archives.upload.store'), [
            'backup_json' => $upload,
        ]);

        $response->assertRedirect(route('archives.upload'));
        $response->assertSessionHas('error');
    }

    /**
     * @return array{0: BiometricDevice, 1: Campus}
     */
    private function makeDevice(): array
    {
        $campus = Campus::query()->create([
            'code' => 'SM',
            'name' => 'San Mateo',
            'is_active' => true,
        ]);

        $device = BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Main gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);

        return [$device, $campus];
    }

    private function makeLog(BiometricDevice $device, string $punchedAt, string $userId = '1001'): BiometricAttendanceLog
    {
        return BiometricAttendanceLog::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => $userId,
            'user_name' => 'Test User',
            'punched_at' => $punchedAt,
            'verify_mode' => 'Fingerprint',
            'punch_state' => 'CheckIn',
        ]);
    }
}
