<?php

namespace App\Services;

use App\Models\BiometricDevice;
use RuntimeException;
use ZkTeco\TCP\Device;
use ZkTeco\Values\User as ZkUser;

class ZkTecoDeviceFingerprintEnroller
{
    public function __construct(
        private readonly ZkTecoInteractiveDeviceSession $interactiveSession,
    ) {}

    /**
     * Start interactive fingerprint capture on the device sensor for an enrolled user.
     */
    public function enroll(
        BiometricDevice $device,
        string $userId,
        int $fingerIndex,
    ): bool {
        $userId = trim($userId);

        if ($userId === '') {
            throw new RuntimeException('User ID is required for fingerprint enrollment.');
        }

        if ($fingerIndex < 0 || $fingerIndex > 9) {
            throw new RuntimeException('Finger index must be between 0 and 9.');
        }

        $readTimeout = (float) config('biometric.device.enroll_timeout_seconds', 90);

        return $this->interactiveSession->run($device, $readTimeout, function (Device $connected) use ($userId, $fingerIndex): bool {
            $enrolled = $connected->users()->all();
            $match = null;

            foreach ($enrolled as $existing) {
                if ((string) $existing->userId === $userId) {
                    $match = $existing;
                    break;
                }
            }

            if ($match === null) {
                throw new RuntimeException(sprintf(
                    'User ID %s is not on the device. Save the user first, then enroll fingerprint.',
                    $userId,
                ));
            }

            $zkUser = new ZkUser(
                uid: $match->uid,
                userId: (string) $match->userId,
                name: $match->name,
                privilege: $match->privilege,
                password: $match->password,
                cardNumber: $match->cardNumber,
                groupId: $match->groupId,
            );

            return $connected->templates()->enroll($zkUser, $fingerIndex);
        });
    }
}
