<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\Campus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricDeviceEditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_form_shows_existing_ip(): void
    {
        $device = $this->makeDevice();

        $this->get(route('devices.edit', $device))
            ->assertOk()
            ->assertSee('10.0.3.23')
            ->assertSee('Edit ZkTeco device');
    }

    #[Test]
    public function can_update_device_ip_address(): void
    {
        $device = $this->makeDevice();

        $this->put(route('devices.update', $device), [
            'name' => 'Greenhills',
            'model' => 'K30',
            'ip_address' => '10.0.3.50',
            'port' => 4370,
            'comm_key' => 0,
        ])->assertRedirect(route('dashboard'));

        $device->refresh();

        $this->assertSame('10.0.3.50', $device->ip_address);
        $this->assertNull($device->last_error);
    }

    private function makeDevice(): BiometricDevice
    {
        $campus = Campus::query()->create([
            'code' => 'gh',
            'name' => 'Greenhills',
            'is_active' => true,
        ]);

        return BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => 'Greenhills',
            'model' => 'K30',
            'ip_address' => '10.0.3.23',
            'port' => 4370,
            'comm_key' => 0,
            'is_active' => true,
            'last_error' => 'old error',
        ]);
    }
}
