<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\Campus;
use App\Services\CollectorInstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiometricDeviceController extends Controller
{
    public function create(): View
    {
        return view('collector.devices.create', [
            'defaultPort' => (int) config('biometric.device.default_port', 4370),
            'defaultModel' => (string) config('biometric.device.default_model', 'K30'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedDevice($request);
        $campus = $this->defaultCampus();
        $port = (int) ($validated['port'] ?? config('biometric.device.default_port', 4370));

        if ($this->deviceIpPortExists($campus->id, $validated['ip_address'], $port)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'A device with this IP and port is already registered.');
        }

        BiometricDevice::query()->create([
            'campus_id' => $campus->id,
            'name' => $validated['name'],
            'model' => $validated['model'] ?? config('biometric.device.default_model', 'K30'),
            'ip_address' => $validated['ip_address'],
            'port' => $port,
            'comm_key' => (int) ($validated['comm_key'] ?? 0),
            'is_active' => true,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Device added: '.$validated['name'].' ('.$validated['ip_address'].':'.$port.')');
    }

    public function edit(BiometricDevice $device): View
    {
        return view('collector.devices.edit', [
            'device' => $device,
        ]);
    }

    public function update(Request $request, BiometricDevice $device): RedirectResponse
    {
        $validated = $this->validatedDevice($request);
        $port = (int) ($validated['port'] ?? $device->port ?? config('biometric.device.default_port', 4370));

        if ($this->deviceIpPortExists((int) $device->campus_id, $validated['ip_address'], $port, $device->id)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'A device with this IP and port is already registered.');
        }

        $device->update([
            'name' => $validated['name'],
            'model' => $validated['model'] ?? $device->model ?? config('biometric.device.default_model', 'K30'),
            'ip_address' => $validated['ip_address'],
            'port' => $port,
            'comm_key' => (int) ($validated['comm_key'] ?? 0),
            'last_error' => null,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Device updated: '.$device->name.' ('.$device->ip_address.':'.$device->port.')');
    }

    public function destroy(BiometricDevice $device): RedirectResponse
    {
        $label = $device->name;
        $device->delete();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Removed device: '.$label);
    }

    /**
     * @return array{name: string, model?: string|null, ip_address: string, port?: int|null, comm_key?: int|null}
     */
    private function validatedDevice(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:64'],
            'ip_address' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'comm_key' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function deviceIpPortExists(int $campusId, string $ipAddress, int $port, ?int $ignoreDeviceId = null): bool
    {
        return BiometricDevice::query()
            ->where('campus_id', $campusId)
            ->where('ip_address', $ipAddress)
            ->where('port', $port)
            ->when($ignoreDeviceId, fn ($query) => $query->where('id', '!=', $ignoreDeviceId))
            ->exists();
    }

    private function defaultCampus(): Campus
    {
        $installation = app(CollectorInstallationService::class);
        $code = $installation->slug();
        $name = $installation->displayName() ?? 'Default campus';

        return Campus::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'is_active' => true],
        );
    }
}
