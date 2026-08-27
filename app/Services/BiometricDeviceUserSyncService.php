<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use Illuminate\Support\Facades\DB;

class BiometricDeviceUserSyncService
{
    /**
     * @param  list<array{device_uid: int, user_id: string, name: string, card_number: ?string, privilege?: string}>  $users
     * @return array<string, array{name: ?string, card_number: ?string}>
     */
    public function sync(BiometricDevice $device, array $users): array
    {
        if ($users === []) {
            return [];
        }

        $syncedAt = now();
        $map = [];

        DB::transaction(function () use ($device, $users, $syncedAt, &$map): void {
            foreach ($users as $user) {
                $name = $user['name'] !== '' ? $user['name'] : null;
                $card = $user['card_number'];
                $privilege = $user['privilege'] ?? 'User';

                $existing = BiometricDeviceUser::query()
                    ->where('biometric_device_id', $device->id)
                    ->where('user_id', $user['user_id'])
                    ->first();

                $needsReexport = $existing !== null && (
                    $existing->name !== $name
                    || $existing->card_number !== $card
                    || $existing->device_uid !== $user['device_uid']
                    || $existing->privilege !== $privilege
                );

                $payload = [
                    'device_uid' => $user['device_uid'],
                    'name' => $name,
                    'card_number' => $card,
                    'privilege' => $privilege,
                    'synced_at' => $syncedAt,
                ];

                if ($existing === null || $needsReexport) {
                    $payload['s3_export_batch_id'] = null;
                    $payload['s3_pushed_at'] = null;
                    $payload['s3_object_key'] = null;
                } else {
                    $payload['s3_export_batch_id'] = $existing->s3_export_batch_id;
                    $payload['s3_pushed_at'] = $existing->s3_pushed_at;
                    $payload['s3_object_key'] = $existing->s3_object_key;
                }

                BiometricDeviceUser::query()->updateOrCreate(
                    [
                        'biometric_device_id' => $device->id,
                        'user_id' => $user['user_id'],
                    ],
                    $payload,
                );

                $map[$user['user_id']] = [
                    'name' => $name,
                    'card_number' => $card,
                ];

                BiometricAttendanceLog::query()
                    ->where('biometric_device_id', $device->id)
                    ->where('user_id', $user['user_id'])
                    ->update([
                        'user_name' => $name,
                        'card_number' => $card,
                    ]);
            }
        });

        return $map;
    }
}
