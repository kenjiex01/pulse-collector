<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Support\ZkTecoMemoryStatistics;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZkTeco\TCP\Device;

class BiometricDeviceMemoryStatsService
{
    /**
     * @return array<string, array{
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     available: bool,
     *     message: ?string
     * }>
     */
    public function readMany(iterable $devices): array
    {
        $results = [];

        foreach ($devices as $device) {
            if (! $device instanceof BiometricDevice) {
                continue;
            }

            $results[(string) $device->id] = $this->readForDevice($device);
        }

        return $results;
    }

    /**
     * @return array{
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     available: bool,
     *     message: ?string
     * }
     */
    public function readForDevice(BiometricDevice $device): array
    {
        $unavailable = [
            'attendance_used' => 0,
            'attendance_capacity' => null,
            'attendance_free' => null,
            'users_used' => 0,
            'users_capacity' => null,
            'users_free' => null,
            'available' => false,
            'message' => 'Device offline or disabled.',
        ];

        if (! $device->is_active) {
            $unavailable['message'] = 'Device is disabled.';

            return $unavailable;
        }

        if (! class_exists(Device::class)) {
            $unavailable['message'] = 'ZkTeco library is not available.';

            return $unavailable;
        }

        try {
            $zkDevice = new Device(
                host: $device->ip_address,
                port: $device->port,
                commKey: $device->comm_key,
                timeout: (float) config('biometric.device.reachability_timeout_seconds', 5),
            );

            $parsed = $zkDevice->session(function (Device $connected): array {
                return ZkTecoMemoryStatistics::parse($connected->readFreeSizesPayload());
            });

            return $this->formatForUi($parsed);
        } catch (Throwable $exception) {
            Log::debug('Biometric device memory stats unavailable.', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'attendance_used' => 0,
                'attendance_capacity' => null,
                'attendance_free' => null,
                'users_used' => 0,
                'users_capacity' => null,
                'users_free' => null,
                'available' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array{
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     fingers_used: int,
     *     fingers_capacity: ?int,
     *     fingers_free: ?int,
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     faces_used: ?int,
     *     faces_capacity: ?int,
     * }  $parsed
     * @return array{
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     available: bool,
     *     message: ?string
     * }
     */
    private function formatForUi(array $parsed): array
    {
        $attendanceUsed = max(0, (int) $parsed['attendance_used']);
        $attendanceCapacity = $parsed['attendance_capacity'];
        $attendanceFree = $parsed['attendance_free'];

        if ($attendanceFree === null && $attendanceCapacity !== null) {
            $attendanceFree = max(0, $attendanceCapacity - $attendanceUsed);
        }

        $usersUsed = max(0, (int) $parsed['users_used']);
        $usersCapacity = $parsed['users_capacity'];
        $usersFree = $parsed['users_free'];

        if ($usersFree === null && $usersCapacity !== null) {
            $usersFree = max(0, $usersCapacity - $usersUsed);
        }

        $hasCapacity = $attendanceCapacity !== null || $usersCapacity !== null;

        return [
            'attendance_used' => $attendanceUsed,
            'attendance_capacity' => $attendanceCapacity,
            'attendance_free' => $attendanceFree,
            'users_used' => $usersUsed,
            'users_capacity' => $usersCapacity,
            'users_free' => $usersFree,
            'available' => $hasCapacity || $attendanceUsed > 0 || $usersUsed > 0,
            'message' => $hasCapacity ? null : 'Device did not report capacity counters.',
        ];
    }
}
