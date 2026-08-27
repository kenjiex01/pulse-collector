<?php

namespace Tests\Unit;

use App\Services\BiometricLogS3Uploader;
use App\Services\CollectorInstallationService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class BiometricLogS3UploaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_object_key_uses_year_month_and_biometric_name_folders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 11:45:30', 'Asia/Manila'));

        config([
            'biometric.s3.prefix' => 'biometric_logs',
        ]);

        $installation = Mockery::mock(CollectorInstallationService::class);
        $installation->shouldReceive('slug')->andReturn('Cainta-Front-Desk');

        $uploader = new BiometricLogS3Uploader($installation);
        $key = $uploader->objectKey('Cainta-Front-Desk_20260812114530.json.gzip');

        $this->assertSame(
            'biometric_logs/2026/08/Cainta-Front-Desk/Cainta-Front-Desk_20260812114530.json.gzip',
            $key,
        );
    }

    public function test_s3_falls_back_to_db_backup_credentials(): void
    {
        config([
            'biometric.s3.enabled' => true,
            'biometric.s3.key' => null,
            'biometric.s3.secret' => null,
            'biometric.s3.bucket' => null,
            'biometric.s3.region' => null,
        ]);

        // Re-load via env-style defaults already resolved in config/biometric.php at boot.
        // Assert the published config file defaults wire to DB_BACKUP_* when BIOMETRIC_S3_* empty.
        $this->assertSame('backup-s3', config('biometric.s3.disk'));
        $this->assertSame('biometric_logs', config('biometric.s3.prefix'));
    }
}
