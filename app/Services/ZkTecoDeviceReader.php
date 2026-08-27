<?php

namespace App\Services;

use App\Models\BiometricDevice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZkTeco\TCP\Device;

class ZkTecoDeviceReader
{
    /**
     * @return array{
     *     attendance: list<array{
     *         device_uid: ?int,
     *         user_id: string,
     *         punched_at: CarbonInterface,
     *         verify_mode: ?string,
     *         punch_state: ?string,
     *     }>,
     *     users: list<array{
     *         device_uid: int,
     *         user_id: string,
     *         name: string,
     *         card_number: ?string,
     *     }>,
     * }
     */
    public function fetchDeviceSnapshot(BiometricDevice $device): array
    {
        if (! class_exists(Device::class)) {
            throw new \RuntimeException(
                'ZkTeco library is missing. Rebuild the desktop app after fetching lib/zkteco-php (bash scripts/fetch-zkteco.sh && composer dump-autoload -o).',
            );
        }

        $zkDevice = new Device(
            host: $device->ip_address,
            port: $device->port,
            commKey: $device->comm_key,
            timeout: (float) config('biometric.device.timeout_seconds', 10),
        );

        try {
            return $zkDevice->session(function (Device $connected): array {
                $attendance = $connected->attendance()->all();
                $mappedAttendance = [];

                foreach ($attendance as $record) {
                    $mappedAttendance[] = [
                        'device_uid' => $record->uid,
                        'user_id' => (string) $record->userId,
                        'punched_at' => $record->recordedAt,
                        'verify_mode' => $record->verifyMode?->name,
                        'punch_state' => $record->punchState?->name,
                    ];
                }

                return [
                    'attendance' => $mappedAttendance,
                    'users' => $this->mapEnrolledUsers($connected->users()->all()),
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('ZkTeco device read failed.', [
                'device_id' => $device->id,
                'ip' => $device->ip_address,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<array{
     *     device_uid: ?int,
     *     user_id: string,
     *     punched_at: CarbonInterface,
     *     verify_mode: ?string,
     *     punch_state: ?string,
     * }>
     */
    public function fetchAttendance(BiometricDevice $device): array
    {
        return $this->fetchDeviceSnapshot($device)['attendance'];
    }

    /**
     * @return list<array{
     *     device_uid: int,
     *     user_id: string,
     *     name: string,
     *     card_number: ?string,
     * }>
     */
    public function fetchDeviceUsers(BiometricDevice $device): array
    {
        if (! class_exists(Device::class)) {
            throw new \RuntimeException(
                'ZkTeco library is missing. Rebuild the desktop app after fetching lib/zkteco-php (bash scripts/fetch-zkteco.sh && composer dump-autoload -o).',
            );
        }

        $zkDevice = new Device(
            host: $device->ip_address,
            port: $device->port,
            commKey: $device->comm_key,
            timeout: (float) config('biometric.device.timeout_seconds', 10),
        );

        try {
            return $zkDevice->session(function (Device $connected): array {
                return $this->mapEnrolledUsers($connected->users()->all());
            });
        } catch (Throwable $exception) {
            Log::warning('ZkTeco device user read failed.', [
                'device_id' => $device->id,
                'ip' => $device->ip_address,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  list<\ZkTeco\Values\User>  $enrolled
     * @return list<array{
     *     device_uid: int,
     *     user_id: string,
     *     name: string,
     *     card_number: ?string,
     * }>
     */
    private function mapEnrolledUsers(array $enrolled): array
    {
        $mappedUsers = [];

        foreach ($enrolled as $user) {
            $card = $user->cardNumber;
            $card = is_string($card) && trim($card) !== '' ? trim($card) : null;

                    $mappedUsers[] = [
                        'device_uid' => $user->uid,
                        'user_id' => (string) $user->userId,
                        'name' => trim($user->name),
                        'card_number' => $card,
                        'privilege' => $user->privilege->name,
                    ];
        }

        return $mappedUsers;
    }
}
