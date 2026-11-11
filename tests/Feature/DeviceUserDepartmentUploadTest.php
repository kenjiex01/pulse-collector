<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\Campus;
use App\Models\Department;
use App\Models\SysLog;
use App\Services\BiometricDeviceUserSyncService;
use App\Services\ZkTecoDeviceUserWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DeviceUserDepartmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_pages_add_and_block_delete_while_in_use(): void
    {
        $response = $this->post(route('departments.store'), ['name' => 'Finance']);

        $response->assertRedirect(route('departments.index'));
        $department = Department::query()->where('name', 'Finance')->firstOrFail();
        $this->assertDatabaseHas('sys_logs', [
            'action' => 'create',
            'table_name' => 'departments',
            'record_id' => $department->id,
        ]);

        [$device] = $this->makeDevice();
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'department_id' => $department->id,
            'synced_at' => now(),
        ]);

        $this->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error');
        $this->assertNull($department->fresh()->deleted_at);
    }

    public function test_template_is_prefilled_once_per_user_id(): void
    {
        [$device, $campus] = $this->makeDevice();
        $second = BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.89',
            'port' => 4370,
            'is_active' => true,
        ]);
        $finance = Department::query()->create(['name' => 'Finance']);

        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'department_id' => $finance->id,
            'synced_at' => now(),
        ]);
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $second->id,
            'device_uid' => 2,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'department_id' => $finance->id,
            'synced_at' => now(),
        ]);
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 3,
            'user_id' => '1002',
            'name' => 'Ben Cruz',
            'synced_at' => now(),
        ]);

        $response = $this->get(route('users.department-template'));
        $response->assertOk();

        $sheet = $this->sheetFromResponse($response);
        $this->assertSame('User ID', $sheet->getCell('A1')->getValue());
        $this->assertSame('Name', $sheet->getCell('B1')->getValue());
        $this->assertSame('Department', $sheet->getCell('C1')->getValue());
        $this->assertSame('1001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Ana Reyes', $sheet->getCell('B2')->getValue());
        $this->assertSame('Finance', $sheet->getCell('C2')->getValue());
        $this->assertSame('1002', $sheet->getCell('A3')->getValue());
        $this->assertSame('Ben Cruz', $sheet->getCell('B3')->getValue());
        $this->assertSame('', (string) $sheet->getCell('C3')->getValue());
        $this->assertNull($sheet->getCell('A4')->getValue());
    }

    public function test_upload_assigns_updates_and_logs_previous_department(): void
    {
        [$device, $campus] = $this->makeDevice();
        $second = BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Gate',
            'model' => 'K30',
            'ip_address' => '192.168.1.90',
            'port' => 4370,
            'is_active' => true,
        ]);
        Department::query()->create(['name' => 'Finance']);
        $hr = Department::query()->create(['name' => 'Human Resources']);

        $lobby = BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'synced_at' => now(),
        ]);
        $gate = BiometricDeviceUser::query()->create([
            'biometric_device_id' => $second->id,
            'device_uid' => 2,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'synced_at' => now(),
        ]);
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 3,
            'user_id' => '1002',
            'name' => 'Ben Cruz',
            'department_id' => $hr->id,
            'synced_at' => now(),
        ]);

        $this->post(route('users.department-upload'), [
            'file' => $this->spreadsheetFile([
                ['1001', 'Ana Reyes', 'Finance'],
                ['1002', 'Ben Cruz', ''],
                ['1003', 'Missing', 'Finance'],
                ['1001', 'Ana Reyes', 'Not A Department'],
            ]),
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Finance', $lobby->fresh()->department->name);
        $this->assertSame('Finance', $gate->fresh()->department->name);
        $this->assertSame($hr->id, BiometricDeviceUser::query()->where('user_id', '1002')->first()->department_id);

        $assigned = SysLog::query()->where('description', 'Assigned department')->first();
        $this->assertNotNull($assigned);
        $this->assertNull($assigned->old_values['department']);
        $this->assertSame('Finance', $assigned->new_values['department']);
        $this->assertSame('1001', $assigned->old_values['user_id']);

        $this->post(route('users.department-upload'), [
            'file' => $this->spreadsheetFile([
                ['1001', 'Ana Reyes', 'Human Resources'],
                ['1001', 'Ana Reyes', 'Human Resources'],
            ]),
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Human Resources', $lobby->fresh()->department->name);
        $updated = SysLog::query()->where('description', 'Updated department')->get();
        $this->assertCount(1, $updated);
        $this->assertSame('Finance', $updated->first()->old_values['department']);
        $this->assertSame('Human Resources', $updated->first()->new_values['department']);
    }

    public function test_creating_a_device_user_saves_the_selected_department(): void
    {
        [$device] = $this->makeDevice();
        $finance = Department::query()->create(['name' => 'Finance']);

        $this->mock(ZkTecoDeviceUserWriter::class, function ($mock) use ($device): void {
            $mock->shouldReceive('create')->once()->andReturnUsing(function () use ($device): array {
                BiometricDeviceUser::query()->create([
                    'biometric_device_id' => $device->id,
                    'device_uid' => 1,
                    'user_id' => '1001',
                    'name' => 'Ana Reyes',
                    'privilege' => 'User',
                    'synced_at' => now(),
                ]);

                return [];
            });
        });

        $this->get(route('users.create', ['device_id' => $device->id]))
            ->assertOk()
            ->assertSee('Department')
            ->assertSee('Finance');

        $response = $this->post(route('users.store'), [
            'device_id' => $device->id,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'privilege' => 'User',
            'department_id' => $finance->id,
        ]);

        $user = BiometricDeviceUser::query()->where('user_id', '1001')->firstOrFail();
        $response->assertRedirect(route('users.edit', $user));
        $this->assertSame($finance->id, $user->department_id);

        $assigned = SysLog::query()->where('description', 'Assigned department')->first();
        $this->assertNotNull($assigned);
        $this->assertNull($assigned->old_values['department']);
        $this->assertSame('Finance', $assigned->new_values['department']);
    }

    public function test_user_list_and_dtr_filter_by_department(): void
    {
        [$device] = $this->makeDevice();
        $finance = Department::query()->create(['name' => 'Finance']);
        $hr = Department::query()->create(['name' => 'Human Resources']);

        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'department_id' => $finance->id,
            'synced_at' => now(),
        ]);
        BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 2,
            'user_id' => '1002',
            'name' => 'Ben Cruz',
            'department_id' => $hr->id,
            'synced_at' => now(),
        ]);

        $users = $this->get(route('users.index', ['department_id' => $finance->id]));
        $users->assertOk();
        $users->assertSee('Ana Reyes');
        $users->assertDontSee('Ben Cruz');
        $users->assertSee('data-file-download', false);
        $users->assertSee('for="department-upload-file"', false);
        $users->assertSee('department-upload-box', false);

        $dtr = $this->get(route('dtr.index', ['department_id' => $hr->id]));
        $dtr->assertOk();
        $dtr->assertSee('name="department_id"', false);
        $dtr->assertSee('Ben Cruz');
        $dtr->assertDontSee('Ana Reyes');
    }

    public function test_device_sync_keeps_saved_department(): void
    {
        [$device] = $this->makeDevice();
        $finance = Department::query()->create(['name' => 'Finance']);
        $user = BiometricDeviceUser::query()->create([
            'biometric_device_id' => $device->id,
            'device_uid' => 1,
            'user_id' => '1001',
            'name' => 'Ana Reyes',
            'department_id' => $finance->id,
            'synced_at' => now(),
        ]);

        app(BiometricDeviceUserSyncService::class)->sync($device, [[
            'device_uid' => 4,
            'user_id' => '1001',
            'name' => 'Ana R. Reyes',
            'card_number' => null,
            'privilege' => 'User',
        ]]);

        $fresh = $user->fresh();
        $this->assertSame($finance->id, $fresh->department_id);
        $this->assertSame('Ana R. Reyes', $fresh->name);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     */
    private function spreadsheetFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['User ID', 'Name', 'Department'], null, 'A1');
        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$line);
            $line++;
        }

        $path = tempnam(sys_get_temp_dir(), 'dept-upload-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'departments.xlsx', null, null, true);
    }

    private function sheetFromResponse(\Illuminate\Testing\TestResponse $response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'dept-template-');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
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
            'name' => 'Lobby',
            'model' => 'K30',
            'ip_address' => '192.168.1.88',
            'port' => 4370,
            'is_active' => true,
        ]);

        return [$device, $campus];
    }
}
