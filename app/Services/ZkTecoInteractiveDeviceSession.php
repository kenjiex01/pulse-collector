<?php

namespace App\Services;

use App\Models\BiometricDevice;
use ReflectionClass;
use RuntimeException;
use Throwable;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Device;
use Illuminate\Support\Facades\Log;
use ZkTeco\Values\User as ZkUser;

class ZkTecoInteractiveDeviceSession
{
    /**
     * @template T
     *
     * @param  callable(Device): T  $callback
     * @return T
     */
    public function run(BiometricDevice $device, float $readTimeoutSeconds, callable $callback): mixed
    {
        if (! class_exists(Device::class)) {
            throw new RuntimeException(
                'ZkTeco library is missing. Rebuild the desktop app after fetching lib/zkteco-php (bash scripts/fetch-zkteco.sh && composer dump-autoload -o).',
            );
        }

        $connectTimeout = (float) config('biometric.device.timeout_seconds', 10);

        $zkDevice = new Device(
            host: $device->ip_address,
            port: $device->port,
            commKey: $device->comm_key,
            timeout: $connectTimeout,
        );

        try {
            return $zkDevice->session(function (Device $connected) use ($callback, $readTimeoutSeconds): mixed {
                $session = $this->resolveSession($connected);
                $previous = $session->readTimeout();
                $session->setReadTimeout($readTimeoutSeconds);

                try {
                    return $callback($connected);
                } finally {
                    $session->setReadTimeout($previous);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('ZkTeco interactive device session failed.', [
                'device_id' => $device->id,
                'ip' => $device->ip_address,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveSession(Device $connected): Session
    {
        $reflection = new ReflectionClass($connected);
        $property = $reflection->getProperty('session');
        $property->setAccessible(true);
        $session = $property->getValue($connected);

        if (! $session instanceof Session) {
            throw new RuntimeException('Device session is not available.');
        }

        return $session;
    }
}
