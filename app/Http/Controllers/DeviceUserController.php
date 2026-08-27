<?php

namespace App\Http\Controllers;

use App\Support\ZkTecoFingerIndex;
use App\Support\ZkTecoUserPrivilege;
use App\Services\ZkTecoDeviceFingerprintEnroller;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Services\BiometricDeviceUserSyncService;
use App\Services\ZkTecoDeviceUserWriter;
use App\Services\ZkTecoDeviceReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DeviceUserController extends Controller
{
    public function __construct(
        private readonly ZkTecoDeviceReader $reader,
        private readonly BiometricDeviceUserSyncService $userSync,
        private readonly ZkTecoDeviceUserWriter $userWriter,
        private readonly ZkTecoDeviceFingerprintEnroller $fingerprintEnroller,
    ) {}

    public function index(Request $request): View
    {
        $deviceId = $request->integer('device_id') ?: null;
        $perPageOptions = config('biometric.users.per_page_options', [10, 25, 50, 100]);
        $defaultPerPage = (int) config('biometric.users.per_page', 25);
        $perPageInput = $request->input('per_page');
        $showAll = $perPageInput === 'all';

        if ($showAll) {
            $perPageSelection = 'all';
        } elseif ($perPageInput !== null && $perPageInput !== '') {
            $perPage = (int) $perPageInput;
            $perPageSelection = in_array($perPage, $perPageOptions, true)
                ? $perPage
                : $defaultPerPage;
        } else {
            $perPageSelection = $defaultPerPage;
        }

        $search = trim((string) $request->input('search', ''));

        $selectedDevice = $deviceId
            ? BiometricDevice::query()->with('campus')->find($deviceId)
            : null;

        $query = BiometricDeviceUser::query()
            ->with(['device.campus'])
            ->when($deviceId, fn ($q) => $q->where('biometric_device_id', $deviceId))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('user_id', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('card_number', 'like', $like);
                });
            });

        $filteredTotal = (clone $query)->count();

        $perPage = $showAll ? max(1, $filteredTotal) : (int) $perPageSelection;

        $users = $query
            ->orderBy('biometric_device_id')
            ->orderBy('user_id')
            ->paginate($perPage)
            ->withQueryString();

        return view('collector.users.index', [
            'users' => $users,
            'devices' => BiometricDevice::query()->with('campus')->orderBy('name')->get(),
            'selectedDeviceId' => $deviceId,
            'selectedDevice' => $selectedDevice,
            'filteredTotal' => $filteredTotal,
            'search' => $search,
            'perPageSelection' => $perPageSelection,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function refreshFromDevice(Request $request, BiometricDevice $device): RedirectResponse
    {
        try {
            $fetched = $this->reader->fetchDeviceUsers($device);
            $this->userSync->sync($device, $fetched);

            return redirect()
                ->route('users.index', ['device_id' => $device->id])
                ->with('success', sprintf(
                    'Synced %d enrolled user(s) from %s.',
                    count($fetched),
                    $device->name,
                ));
        } catch (Throwable $exception) {
            return redirect()
                ->route('users.index', ['device_id' => $device->id])
                ->with('error', 'Could not read users from device: '.$exception->getMessage());
        }
    }

    public function create(Request $request): View|RedirectResponse
    {
        $deviceId = $request->integer('device_id');
        if (! $deviceId) {
            return redirect()
                ->route('users.index')
                ->with('warning', 'Select a device first, then add a user.');
        }

        $device = BiometricDevice::query()->findOrFail($deviceId);

        return view('collector.users.create', [
            'device' => $device,
            'privilegeOptions' => ZkTecoUserPrivilege::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:biometric_devices,id'],
            'user_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'card_number' => ['nullable', 'string', 'max:64'],
            'privilege' => ['required', 'string', Rule::in(ZkTecoUserPrivilege::keys())],
            'device_uid' => ['nullable', 'integer', 'min:1'],
        ]);

        $device = BiometricDevice::query()->findOrFail((int) $validated['device_id']);

        $localExists = BiometricDeviceUser::query()
            ->where('biometric_device_id', $device->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($localExists) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This user ID is already stored for this device. Use Edit to update, or refresh from device.');
        }

        try {
            $this->userWriter->create(
                $device,
                $validated['user_id'],
                $validated['name'],
                $validated['card_number'] ?? null,
                ZkTecoUserPrivilege::toEnum($validated['privilege']),
                isset($validated['device_uid']) ? (int) $validated['device_uid'] : null,
            );
        } catch (Throwable $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Could not enroll user on device: '.$exception->getMessage());
        }

        return redirect()
            ->route('users.edit', BiometricDeviceUser::query()
                ->where('biometric_device_id', $device->id)
                ->where('user_id', $validated['user_id'])
                ->firstOrFail())
            ->with('success', 'User '.$validated['user_id'].' saved on '.$device->name.'. You can enroll a fingerprint below.');
    }

    public function edit(BiometricDeviceUser $deviceUser): View
    {
        $deviceUser->load('device');

        return view('collector.users.edit', [
            'deviceUser' => $deviceUser,
            'device' => $deviceUser->device,
            'privilegeOptions' => ZkTecoUserPrivilege::options(),
            'fingerOptions' => ZkTecoFingerIndex::options(),
        ]);
    }

    public function enrollFingerprint(Request $request, BiometricDeviceUser $deviceUser): RedirectResponse
    {
        $validated = $request->validate([
            'finger_index' => ['required', 'integer', Rule::in(ZkTecoFingerIndex::keys())],
        ]);

        $fingerIndex = (int) $validated['finger_index'];
        $fingerLabel = ZkTecoFingerIndex::options()[$fingerIndex] ?? 'Finger '.$fingerIndex;

        $device = $deviceUser->device;
        if ($device === null) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Device record is missing for this user.');
        }

        try {
            $captured = $this->fingerprintEnroller->enroll(
                $device,
                $deviceUser->user_id,
                $fingerIndex,
            );
        } catch (Throwable $exception) {
            return $this->redirectEnrollResult($deviceUser, [
                'status' => 'error',
                'title' => 'Fingerprint enrollment failed',
                'message' => $exception->getMessage(),
                'detail' => 'User '.$deviceUser->user_id.' · '.$fingerLabel.' · '.$device->name,
            ]);
        }

        if (! $captured) {
            return $this->redirectEnrollResult($deviceUser, [
                'status' => 'warning',
                'title' => 'Enrollment not completed',
                'message' => 'The device did not confirm a successful capture. Press the same finger on the K30 sensor when it beeps (usually three scans), then try again.',
                'detail' => 'User '.$deviceUser->user_id.' · '.$fingerLabel,
            ], $fingerIndex);
        }

        return $this->redirectEnrollResult($deviceUser, [
            'status' => 'success',
            'title' => 'Fingerprint enrollment complete',
            'message' => 'The K30 saved the fingerprint for user '.$deviceUser->user_id.'. They can verify on the device now.',
            'detail' => $fingerLabel.' · '.$device->name.' ('.$device->ip_address.') · '.now()->format('Y-m-d H:i:s'),
        ], $fingerIndex);
    }

    /**
     * @param  array{status: string, title: string, message: string, detail?: string}  $result
     */
    private function redirectEnrollResult(
        BiometricDeviceUser $deviceUser,
        array $result,
        ?int $fingerIndex = null,
    ): RedirectResponse {
        $url = route('users.edit', $deviceUser).'#enroll-fingerprint';

        return redirect()
            ->to($url)
            ->withInput($fingerIndex !== null ? ['finger_index' => $fingerIndex] : [])
            ->with('enroll_result', $result);
    }

    public function update(Request $request, BiometricDeviceUser $deviceUser): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'string',
                'max:64',
                Rule::unique('biometric_device_users', 'user_id')
                    ->where('biometric_device_id', $deviceUser->biometric_device_id)
                    ->ignore($deviceUser->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'card_number' => ['nullable', 'string', 'max:64'],
            'privilege' => ['required', 'string', Rule::in(ZkTecoUserPrivilege::keys())],
        ]);

        $device = $deviceUser->device;
        if ($device === null) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Device record is missing for this user.');
        }

        $previousUserId = $deviceUser->user_id;

        try {
            $this->userWriter->update(
                $device,
                $previousUserId,
                $validated['user_id'],
                $validated['name'],
                $validated['card_number'] ?? null,
                ZkTecoUserPrivilege::toEnum($validated['privilege']),
                $deviceUser->device_uid,
            );
        } catch (Throwable $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Could not push update to device: '.$exception->getMessage());
        }

        $pushedUserId = $validated['user_id'];

        return redirect()
            ->route('users.index', ['device_id' => $device->id])
            ->with('success', sprintf(
                'Pushed changes for user %s to %s (%s:%d).',
                $pushedUserId,
                $device->name,
                $device->ip_address,
                $device->port,
            ));
    }
}
