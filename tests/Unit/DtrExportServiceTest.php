<?php

namespace Tests\Unit;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\Campus;
use App\Services\DtrExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pair_punches_for_day_uses_check_in_and_check_out_states(): void
    {
        $device = $this->makeDevice();
        $punches = [
            $this->makeLog($device, '2026-08-01 08:00:00', 'CheckIn'),
            $this->makeLog($device, '2026-08-01 12:00:00', 'CheckOut'),
            $this->makeLog($device, '2026-08-01 13:00:00', 'CheckIn'),
            $this->makeLog($device, '2026-08-01 17:00:00', 'CheckOut'),
        ];

        $pairs = app(DtrExportService::class)->pairPunchesForDay($punches);

        $this->assertSame([
            ['in' => '08:00:00', 'out' => '12:00:00'],
            ['in' => '13:00:00', 'out' => '17:00:00'],
        ], $pairs);
    }

    public function test_pair_punches_for_day_alternates_undefined_states(): void
    {
        $device = $this->makeDevice();
        $punches = [
            $this->makeLog($device, '2026-08-01 08:01:00', null),
            $this->makeLog($device, '2026-08-01 17:02:00', null),
        ];

        $pairs = app(DtrExportService::class)->pairPunchesForDay($punches);

        $this->assertSame([
            ['in' => '08:01:00', 'out' => '17:02:00'],
        ], $pairs);
    }

    public function test_timesheet_includes_letterhead_and_employee_name(): void
    {
        $device = $this->makeDevice();
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Test User',
            'privilege' => 0,
            'synced_at' => now(),
        ]);
        $this->makeLog($device, '2026-08-05 08:00:00', 'CheckIn', '1001');
        $this->makeLog($device, '2026-08-05 17:00:00', 'CheckOut', '1001');

        $workbooks = app(DtrExportService::class)->buildWorkbooks(
            Carbon::parse('2026-08-05'),
            Carbon::parse('2026-08-05'),
            [[
                'device_id' => $device->id,
                'user_id' => '1001',
            ]],
        );

        $sheet = $workbooks[0]['spreadsheet']->getActiveSheet();

        $this->assertSame('2026-08-05_to_2026-08-05_Test_User', $workbooks[0]['filename']);
        $this->assertSame('ICCT COLLEGES FOUNDATION INC.', $sheet->getCell('A1')->getValue());
        $this->assertSame('', $sheet->getCell('A2')->getValue());
        $this->assertSame('', $sheet->getCell('A3')->getValue());
        $this->assertSame('Employee: Test User (1001)', $sheet->getCell('A8')->getValue());
        $this->assertSame('08/05/2026', $sheet->getCell('A10')->getValue());
        $this->assertSame('8:00 am', $sheet->getCell('B10')->getValue());
        $this->assertSame('5:00 pm', $sheet->getCell('C10')->getValue());
    }

    private function makeDevice(): BiometricDevice
    {
        $campus = Campus::query()->create([
            'code' => 'CT',
            'name' => 'Cainta',
            'is_active' => true,
        ]);

        return BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Main gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
            'is_active' => true,
        ]);
    }

    private function makeLog(
        BiometricDevice $device,
        string $punchedAt,
        ?string $punchState,
        string $userId = '1001',
    ): BiometricAttendanceLog {
        return BiometricAttendanceLog::query()->create([
            'campus_id' => $device->campus_id,
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => $userId,
            'user_name' => 'Test User',
            'punched_at' => $punchedAt,
            'verify_mode' => 'Fingerprint',
            'punch_state' => $punchState,
        ]);
    }
}
