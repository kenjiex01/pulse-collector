<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use RuntimeException;
use ZkTeco\Enums\Privilege;
use ZkTeco\TCP\Device;
use ZkTeco\Values\User as ZkUser;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZkTecoDeviceUserWriter
{
    public function __construct(
        private readonly BiometricDeviceUserSyncService $userSync,
    ) {}

    /**
     * Create a new enrolled user on the device (fails if user ID already exists).
     *
     * @return array{device_uid: int, user_id: string, name: string, card_number: ?string}
     */
    public function create(
        BiometricDevice $device,
        string $userId,
        string $name,
        ?string $cardNumber,
        Privilege $privilege,
        ?int $deviceUid = null,
    ): array {
        $userId = trim($userId);
        $name = trim($name);
        $cardNumber = $this->normalizeCard($cardNumber);

        $mapped = $this->withDeviceSession($device, function (Device $connected) use ($userId, $name, $cardNumber, $privilege, $deviceUid): array {
            $enrolled = $connected->users()->all();

            foreach ($enrolled as $existing) {
                if ((string) $existing->userId === $userId) {
                    throw new RuntimeException(sprintf('User ID %s is already enrolled on this device.', $userId));
                }
            }

            $uid = $this->resolveUidForCreate($enrolled, $deviceUid);

            foreach ($enrolled as $existing) {
                if ($existing->uid === $uid && (string) $existing->userId !== $userId) {
                    throw new RuntimeException(sprintf('Device slot UID %d is already in use.', $uid));
                }
            }

            $zkUser = new ZkUser(
                uid: $uid,
                userId: $userId,
                name: $name,
                privilege: $privilege,
                cardNumber: $cardNumber,
            );

            $connected->users()->save($zkUser);

            return [
                'device_uid' => $uid,
                'user_id' => $userId,
                'name' => $name,
                'card_number' => $cardNumber,
                'privilege' => $privilege->name,
            ];
        });

        $this->userSync->sync($device, [$mapped]);

        return $mapped;
    }

    /**
     * Update user fields on the device (same UID slot). User ID (employee no.) may be renamed.
     *
     * @return array{device_uid: int, user_id: string, name: string, card_number: ?string}
     */
    public function update(
        BiometricDevice $device,
        string $previousUserId,
        string $newUserId,
        string $name,
        ?string $cardNumber,
        Privilege $privilege,
        ?int $deviceUid = null,
    ): array {
        $previousUserId = trim($previousUserId);
        $newUserId = trim($newUserId);
        $name = trim($name);
        $cardNumber = $this->normalizeCard($cardNumber);

        if ($previousUserId === '' || $newUserId === '') {
            throw new RuntimeException('User ID is required.');
        }

        $mapped = $this->withDeviceSession($device, function (Device $connected) use ($deviceUid, $previousUserId, $newUserId, $name, $cardNumber, $privilege): array {
            $enrolled = $connected->users()->all();
            $match = $this->findEnrolledUser($enrolled, $previousUserId, $deviceUid);

            if ($match === null) {
                throw new RuntimeException(sprintf(
                    'User ID %s was not found on the device. Use Refresh from device, then try again.',
                    $previousUserId,
                ));
            }

            if ($newUserId !== $previousUserId) {
                foreach ($enrolled as $existing) {
                    if ($existing->uid !== $match->uid && (string) $existing->userId === $newUserId) {
                        throw new RuntimeException(sprintf('User ID %s is already enrolled on this device.', $newUserId));
                    }
                }
            }

            $uid = $match->uid;

            $zkUser = new ZkUser(
                uid: $uid,
                userId: $newUserId,
                name: $name,
                privilege: $privilege,
                password: $match->password,
                cardNumber: $cardNumber,
                groupId: $match->groupId,
            );

            $connected->users()->save($zkUser);

            $verified = $this->findEnrolledUser($connected->users()->all(), $newUserId, $uid);
            if ($verified === null) {
                throw new RuntimeException('Device did not confirm the user after save.');
            }

            return [
                'device_uid' => $uid,
                'user_id' => $newUserId,
                'name' => $name,
                'card_number' => $cardNumber,
                'privilege' => $privilege->name,
            ];
        });

        if ($previousUserId !== $newUserId) {
            BiometricDeviceUser::query()
                ->where('biometric_device_id', $device->id)
                ->where('user_id', $previousUserId)
                ->delete();
        }

        $this->userSync->sync($device, [$mapped]);

        return $mapped;
    }

    /**
     * @param  list<ZkUser>  $enrolled
     */
    private function findEnrolledUser(array $enrolled, string $userId, ?int $deviceUid): ?ZkUser
    {
        foreach ($enrolled as $existing) {
            if ((string) $existing->userId === $userId) {
                return $existing;
            }
        }

        if ($deviceUid === null) {
            return null;
        }

        foreach ($enrolled as $existing) {
            if ($existing->uid === $deviceUid) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * @param  list<ZkUser>  $enrolled
     */
    private function resolveUidForCreate(array $enrolled, ?int $deviceUid): int
    {
        if ($deviceUid !== null) {
            if ($deviceUid < 1) {
                throw new RuntimeException('Device UID must be at least 1.');
            }

            return $deviceUid;
        }

        $max = 0;
        foreach ($enrolled as $user) {
            $max = max($max, $user->uid);
        }

        return $max + 1;
    }

    private function normalizeCard(?string $cardNumber): ?string
    {
        if ($cardNumber === null) {
            return null;
        }

        $cardNumber = trim($cardNumber);

        return $cardNumber === '' ? null : $cardNumber;
    }

    /**
     * @template T
     *
     * @param  callable(Device): T  $callback
     * @return T
     */
    private function withDeviceSession(BiometricDevice $device, callable $callback): mixed
    {
        if (! class_exists(Device::class)) {
            throw new RuntimeException(
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
            return $zkDevice->session($callback);
        } catch (Throwable $exception) {
            Log::warning('ZkTeco device user write failed.', [
                'device_id' => $device->id,
                'ip' => $device->ip_address,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
