<?php

namespace Tests\Feature;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Campus;
use App\Models\CollectorInstallation;
use App\Services\BiometricLogRetentionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BiometricLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometric.timestamps_stored_as_utc' => false]);
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        $directory = storage_path('app/biometric-deleted-logs');
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }

        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_retention_months_can_be_saved_from_dashboard(): void
    {
        $response = $this->post(route('settings.log-retention'), [
            'log_retention_months' => 3,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(3, CollectorInstallation::current()->log_retention_months);
    }

    public function test_empty_retention_does_not_delete_logs(): void
    {
        [$device] = $this->makeDevice();
        $this->makeLog($device, '2025-01-01 08:00:00');

        $this->post(route('settings.log-retention'), [
            'log_retention_months' => '',
        ])->assertRedirect(route('dashboard'));

        $this->assertNull(CollectorInstallation::current()->log_retention_months);
        $this->assertSame(1, BiometricAttendanceLog::query()->count());
    }

    public function test_prune_archives_old_logs_to_json_then_deletes_them(): void
    {
        [$device] = $this->makeDevice();
        $old = $this->makeLog($device, '2026-04-01 08:00:00', '1001');
        $kept = $this->makeLog($device, '2026-07-01 08:00:00', '1002');

        CollectorInstallation::current()->update(['log_retention_months' => 3]);

        $summary = app(BiometricLogRetentionService::class)->pruneExpiredLogs();

        $this->assertSame(1, $summary['pruned']);
        $this->assertNotEmpty($summary['files']);
        $this->assertSame('2026-05-17', $summary['cutoff_at']);
        $this->assertFalse(BiometricAttendanceLog::query()->whereKey($old->id)->exists());
        $this->assertTrue(BiometricAttendanceLog::query()->whereKey($kept->id)->exists());

        $path = storage_path('app/biometric-deleted-logs/'.$summary['files'][0]);
        $this->assertFileExists($path);

        $payload = json_decode((string) File::get($path), true);
        $this->assertSame('deleted_attendance', $payload['kind']);
        $this->assertSame(3, $payload['retention_months']);
        $this->assertSame(1, $payload['logs_count']);
        $this->assertSame('1001', $payload['logs'][0]['user_id']);
        $this->assertSame('2026-04-01 08:00:00', $payload['logs'][0]['punched_at']);
    }

    public function test_saving_retention_does_not_prune_until_collect(): void
    {
        [$device] = $this->makeDevice();
        $this->makeLog($device, '2026-01-15 08:00:00');
        $this->makeLog($device, '2026-08-01 08:00:00');

        $this->post(route('settings.log-retention'), [
            'log_retention_months' => 2,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(2, CollectorInstallation::current()->log_retention_months);
        $this->assertSame(2, BiometricAttendanceLog::query()->count());
        $this->assertDirectoryDoesNotExist(storage_path('app/biometric-deleted-logs'));
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
