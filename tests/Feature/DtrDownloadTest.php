<?php

namespace Tests\Feature;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\Campus;
use App\Services\DtrExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dtr_index_shows_download_form(): void
    {
        [$device] = $this->makeDeviceWithUser();

        $response = $this->get(route('dtr.index'));

        $response->assertOk();
        $response->assertSee('Download DTR');
        $response->assertSee((string) $device->id.':1001');
    }

    public function test_dtr_download_returns_csv_for_selected_users(): void
    {
        [$device] = $this->makeDeviceWithUser();
        BiometricAttendanceLog::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'user_name' => 'Ana Reyes',
            'punched_at' => '2026-08-10 08:00:00',
            'verify_mode' => 'Fingerprint',
            'punch_state' => 'CheckIn',
        ]);
        BiometricAttendanceLog::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'user_name' => 'Ana Reyes',
            'punched_at' => '2026-08-10 17:00:00',
            'verify_mode' => 'Fingerprint',
            'punch_state' => 'CheckOut',
        ]);

        $response = $this->post(route('dtr.download'), [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-10',
            'users' => [DtrExportService::userKey($device->id, '1001')],
        ]);

        $response->assertOk();
        $response->assertDownload('dtr_2026-08-10_2026-08-10.csv');
        $this->assertStringContainsString('Employee: Ana Reyes (1001)', $response->streamedContent());
        $this->assertStringContainsString('08:00:00', $response->streamedContent());
    }

    public function test_dtr_download_requires_at_least_one_user(): void
    {
        $response = $this->from(route('dtr.index'))
            ->post(route('dtr.download'), [
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
                'users' => [],
            ]);

        $response->assertRedirect(route('dtr.index'));
        $response->assertSessionHasErrors('users');
    }

    /**
     * @return array{0: BiometricDevice, 1: Campus}
     */
    private function makeDeviceWithUser(): array
    {
        $campus = Campus::query()->create([
            'code' => 'SM',
            'name' => 'San Mateo',
            'is_active' => true,
        ]);

        $device = BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Lobby',
            'model' => 'K30',
            'ip_address' => '192.168.1.88',
            'port' => 4370,
            'is_active' => true,
        ]);

        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'privilege' => 0,
            'synced_at' => now(),
        ]);

        return [$device, $campus];
    }
}
