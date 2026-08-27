<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Services\BiometricDeviceReachabilityService;
use App\Support\CollectRunTracker;
use Illuminate\Http\JsonResponse;

class DeviceStatusController extends Controller
{
    public function index(BiometricDeviceReachabilityService $reachability, CollectRunTracker $tracker): JsonResponse
    {
        $devices = BiometricDevice::query()
            ->orderBy('campus_id')
            ->orderBy('name')
            ->get();

        $collecting = $tracker->isRunning();
        $checks = $collecting ? [] : $reachability->checkMany($devices);

        $payload = $devices->map(function (BiometricDevice $device) use ($checks, $collecting): array {
            $check = $checks[$device->id] ?? [
                'online' => ! $collecting,
                'latency_ms' => null,
                'message' => $collecting ? 'Collecting logs — status check paused so the device session stays open.' : 'Not checked',
                'status_label' => $collecting ? 'collecting' : 'offline',
                'checked_at' => now()->toIso8601String(),
            ];

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
            ];
        })->values();

        return response()->json([
            'devices' => $payload,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
