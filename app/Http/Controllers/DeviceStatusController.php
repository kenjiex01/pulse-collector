<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Services\BiometricDeviceMemoryStatsService;
use App\Services\BiometricDeviceReachabilityService;
use App\Support\CollectRunTracker;
use Illuminate\Http\JsonResponse;

class DeviceStatusController extends Controller
{
    public function index(
        BiometricDeviceReachabilityService $reachability,
        BiometricDeviceMemoryStatsService $memoryStats,
        CollectRunTracker $tracker,
    ): JsonResponse {
        $devices = BiometricDevice::query()
            ->orderBy('campus_id')
            ->orderBy('name')
            ->get();

        $collecting = $tracker->isRunning();
        $checks = $collecting ? [] : $reachability->checkMany($devices);

        $storageByDevice = [];
        if (! $collecting) {
            $onlineDevices = $devices->filter(function (BiometricDevice $device) use ($checks): bool {
                $check = $checks[$device->id] ?? null;

                return $device->is_active && is_array($check) && ($check['online'] ?? false);
            });
            $storageByDevice = $memoryStats->readMany($onlineDevices);
        }

        $payload = $devices->map(function (BiometricDevice $device) use ($checks, $collecting, $storageByDevice): array {
            $check = $checks[$device->id] ?? [
                'online' => ! $collecting,
                'latency_ms' => null,
                'message' => $collecting ? 'Collecting logs — status check paused so the device session stays open.' : 'Not checked',
                'status_label' => $collecting ? 'collecting' : 'offline',
                'checked_at' => now()->toIso8601String(),
            ];

            $storage = $storageByDevice[(string) $device->id] ?? null;

            return [
                'id' => $device->id,
                'online' => $check['online'],
                'ping_ok' => $check['ping_ok'] ?? false,
                'tcp_ok' => $check['tcp_ok'] ?? $check['online'],
                'latency_ms' => $check['latency_ms'],
                'ping_ms' => $check['ping_ms'] ?? null,
                'message' => $check['message'],
                'status_label' => $check['status_label'] ?? ($check['online'] ? 'online' : 'offline'),
                'checked_at' => $check['checked_at'],
                'disabled' => ! $device->is_active,
                'last_error' => $device->last_error,
                'last_collected_at' => $device->last_collected_at?->toIso8601String(),
                'storage' => $storage,
            ];
        })->values();

        return response()->json([
            'devices' => $payload,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
