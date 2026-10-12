<?php

namespace Tests\Feature;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Campus;
use App\Models\CollectorInstallation;
use App\Services\BiometricLogCollector;
use App\Services\CollectorInstallationService;
use App\Services\BiometricLogJsonExporter;
use App\Services\BiometricLogS3Uploader;
use App\Services\BiometricDeviceUserSyncService;
use App\Services\ZkTecoDeviceReader;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class BiometricLogCollectorInsertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inserts_new_punches_and_ignores_duplicates(): void
    {
        CollectorInstallation::current()->update([
            'log_retention_months' => 3,
        ]);

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

        $punchedAt = CarbonImmutable::parse('2026-08-17 08:15:00');

        $reader = Mockery::mock(ZkTecoDeviceReader::class);
        $reader->shouldNotReceive('clearAttendance');
        $reader->shouldReceive('fetchDeviceSnapshot')->twice()->andReturn([
            'attendance' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'punched_at' => $punchedAt,
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
                [
                    'device_uid' => 2,
                    'user_id' => '1001',
                    'punched_at' => $punchedAt->addMinutes(1),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckOut',
                ],
            ],
            'users' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'name' => 'Test User',
                    'card_number' => null,
                    'privilege' => 'User',
                ],
            ],
        ]);

        $uploader = Mockery::mock(BiometricLogS3Uploader::class);
        $uploader->shouldReceive('isConfigured')->andReturn(false);

        $collector = new BiometricLogCollector(
            $reader,
            app(BiometricDeviceUserSyncService::class),
            app(BiometricLogJsonExporter::class),
            $uploader,
            app(CollectorInstallationService::class),
        );

        $first = $collector->run();
        $second = $collector->run();

        $this->assertSame(2, $first['logs_inserted']);
        $this->assertSame(0, $second['logs_inserted']);
        $this->assertSame(2, BiometricAttendanceLog::query()->count());
        $this->assertSame($device->id, BiometricAttendanceLog::query()->first()->biometric_device_id);
    }

    public function test_ignores_punches_before_configured_start_date(): void
    {
        CollectorInstallation::current()->update([
            'attendance_start_date' => '2026-08-17',
            'log_retention_months' => 3,
        ]);

        $campus = Campus::query()->create([
            'code' => 'SM',
            'name' => 'San Mateo',
            'is_active' => true,
        ]);

        BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Main gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);

        $reader = Mockery::mock(ZkTecoDeviceReader::class);
        $reader->shouldNotReceive('clearAttendance');
        $reader->shouldReceive('fetchDeviceSnapshot')->once()->andReturn([
            'attendance' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'punched_at' => CarbonImmutable::parse('2026-08-16 23:59:59'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
                [
                    'device_uid' => 2,
                    'user_id' => '1001',
                    'punched_at' => CarbonImmutable::parse('2026-08-17 08:15:00'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
            ],
            'users' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'name' => 'Test User',
                    'card_number' => null,
                    'privilege' => 'User',
                ],
            ],
        ]);

        $uploader = Mockery::mock(BiometricLogS3Uploader::class);
        $uploader->shouldReceive('isConfigured')->andReturn(false);

        $collector = new BiometricLogCollector(
            $reader,
            app(BiometricDeviceUserSyncService::class),
            app(BiometricLogJsonExporter::class),
            $uploader,
            app(CollectorInstallationService::class),
        );

        $summary = $collector->run();

        $this->assertSame(1, $summary['logs_inserted']);
        $this->assertSame(1, BiometricAttendanceLog::query()->count());
        $this->assertSame(
            '2026-08-17 08:15:00',
            BiometricAttendanceLog::query()->firstOrFail()->punched_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_backs_up_device_attendance_json_before_clearing_expired_logs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00', 'Asia/Manila'));
        CollectorInstallation::current()->update([
            'log_retention_months' => 2,
        ]);

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

        $reader = Mockery::mock(ZkTecoDeviceReader::class);
        $reader->shouldReceive('fetchDeviceSnapshot')->twice()->andReturn([
            'attendance' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'punched_at' => CarbonImmutable::parse('2026-01-01 08:00:00'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
                [
                    'device_uid' => 2,
                    'user_id' => '1002',
                    'punched_at' => CarbonImmutable::parse('2026-08-01 08:00:00'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
            ],
            'users' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'name' => 'Old User',
                    'card_number' => null,
                    'privilege' => 'User',
                ],
                [
                    'device_uid' => 2,
                    'user_id' => '1002',
                    'name' => 'Recent User',
                    'card_number' => null,
                    'privilege' => 'User',
                ],
            ],
        ]);
        $reader->shouldReceive('clearAttendance')->once();

        $uploader = Mockery::mock(BiometricLogS3Uploader::class);
        $uploader->shouldReceive('isConfigured')->andReturn(false);

        $collector = new BiometricLogCollector(
            $reader,
            app(BiometricDeviceUserSyncService::class),
            app(BiometricLogJsonExporter::class),
            $uploader,
            app(CollectorInstallationService::class),
        );

        $summary = $collector->run();
        $second = $collector->run();

        $this->assertSame([], $summary['errors']);
        $this->assertSame([], $second['errors']);
        $this->assertSame('2026-08', $device->fresh()->attendance_cleared_through_month);
        $this->assertSame(1, BiometricAttendanceLog::query()->count());
        $this->assertSame('1002', BiometricAttendanceLog::query()->firstOrFail()->user_id);

        $files = File::files(storage_path('app/biometric-deleted-logs'));
        $deviceClear = collect($files)->first(
            fn ($file) => str_contains($file->getFilename(), '_device_cleared_'),
        );
        $this->assertNotNull($deviceClear);

        $payload = json_decode((string) File::get($deviceClear->getPathname()), true);
        $this->assertSame('deleted_attendance', $payload['kind']);
        $this->assertSame('device_retention_clear', $payload['reason']);
        $this->assertSame(2, $payload['logs_count']);
        $this->assertSame(1, $payload['expired_count']);
        $userIds = collect($payload['logs'])->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing(['1001', '1002'], $userIds);

        Carbon::setTestNow();
        File::deleteDirectory(storage_path('app/biometric-deleted-logs'));
    }

    public function test_does_not_clear_device_when_backup_cannot_be_written(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00', 'Asia/Manila'));
        CollectorInstallation::current()->update([
            'log_retention_months' => 2,
        ]);

        $campus = Campus::query()->create([
            'code' => 'SM',
            'name' => 'San Mateo',
            'is_active' => true,
        ]);

        BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Main gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);

        $blocked = storage_path('app/biometric-deleted-logs');
        File::deleteDirectory($blocked);
        File::put($blocked, 'not-a-directory');

        $reader = Mockery::mock(ZkTecoDeviceReader::class);
        $reader->shouldReceive('fetchDeviceSnapshot')->once()->andReturn([
            'attendance' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'punched_at' => CarbonImmutable::parse('2026-01-01 08:00:00'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
            ],
            'users' => [],
        ]);
        $reader->shouldNotReceive('clearAttendance');

        $uploader = Mockery::mock(BiometricLogS3Uploader::class);
        $uploader->shouldReceive('isConfigured')->andReturn(false);

        $collector = new BiometricLogCollector(
            $reader,
            app(BiometricDeviceUserSyncService::class),
            app(BiometricLogJsonExporter::class),
            $uploader,
            app(CollectorInstallationService::class),
        );

        $summary = $collector->run();

        $this->assertNotEmpty($summary['errors']);
        $this->assertStringContainsString('1001', BiometricAttendanceLog::query()->value('user_id') ?? '');

        File::delete($blocked);
        Carbon::setTestNow();
    }

    public function test_does_not_clear_device_before_month_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Manila'));
        CollectorInstallation::current()->update([
            'log_retention_months' => 2,
        ]);

        $campus = Campus::query()->create([
            'code' => 'SM',
            'name' => 'San Mateo',
            'is_active' => true,
        ]);

        BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Main gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);

        $reader = Mockery::mock(ZkTecoDeviceReader::class);
        $reader->shouldReceive('fetchDeviceSnapshot')->once()->andReturn([
            'attendance' => [
                [
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'punched_at' => CarbonImmutable::parse('2026-01-01 08:00:00'),
                    'verify_mode' => 'Fingerprint',
                    'punch_state' => 'CheckIn',
                ],
            ],
            'users' => [],
        ]);
        $reader->shouldNotReceive('clearAttendance');

        $uploader = Mockery::mock(BiometricLogS3Uploader::class);
        $uploader->shouldReceive('isConfigured')->andReturn(false);

        $collector = new BiometricLogCollector(
            $reader,
            app(BiometricDeviceUserSyncService::class),
            app(BiometricLogJsonExporter::class),
            $uploader,
            app(CollectorInstallationService::class),
        );

        $summary = $collector->run();

        $this->assertSame([], $summary['errors']);
        $this->assertSame(0, BiometricAttendanceLog::query()->count());
        Carbon::setTestNow();
    }
}
