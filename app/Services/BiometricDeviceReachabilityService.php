<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Support\NativePhpSystemPhp;
use Illuminate\Support\Facades\Process;

class BiometricDeviceReachabilityService
{
    /**
     * @return array{
     *     online: bool,
     *     ping_ok: bool,
     *     tcp_ok: bool,
     *     latency_ms: ?int,
     *     ping_ms: ?int,
     *     message: ?string,
     *     status_label: string,
     * }
     */
    public function check(BiometricDevice $device): array
    {
        if (! $device->is_active) {
            return [
                'online' => false,
                'ping_ok' => false,
                'tcp_ok' => false,
                'latency_ms' => null,
                'ping_ms' => null,
                'message' => 'Device is disabled for collection.',
                'status_label' => 'disabled',
            ];
        }

        $ping = $this->ping($device->ip_address);
        $tcp = $this->tcp($device->ip_address, $device->port);

        if ($tcp['ok']) {
            return [
                'online' => true,
                'ping_ok' => $ping['ok'],
                'tcp_ok' => true,
                'latency_ms' => $tcp['latency_ms'],
                'ping_ms' => $ping['latency_ms'],
                'message' => null,
                'status_label' => 'online',
            ];
        }

        if ($ping['ok']) {
            return [
                'online' => false,
                'ping_ok' => true,
                'tcp_ok' => false,
                'latency_ms' => $tcp['latency_ms'],
                'ping_ms' => $ping['latency_ms'],
                'message' => sprintf(
                    'Ping OK, but TCP port %d is not accepting connections. Enable network/comm on the ZkTeco device and confirm the port (default 4370). %s',
                    $device->port,
                    $tcp['error'] ?? '',
                ).$this->localNetworkHint($tcp['errno'] ?? null, $tcp['error'] ?? null),
                'status_label' => 'port closed',
            ];
        }

        return [
            'online' => false,
            'ping_ok' => false,
            'tcp_ok' => false,
            'latency_ms' => $tcp['latency_ms'],
            'ping_ms' => $ping['latency_ms'],
            'message' => trim(($ping['error'] ?? 'Host unreachable.').' '.($tcp['error'] ?? ''))
                .$this->localNetworkHint($tcp['errno'] ?? null, $tcp['error'] ?? null),
            'status_label' => 'offline',
        ];
    }

    private function localNetworkHint(?int $errno, ?string $error): string
    {
        if (! config('nativephp-internal.running')) {
            return '';
        }

        $noRoute = $errno === 65
            || ($error !== null && str_contains(strtolower($error), 'no route to host'));

        if (! $noRoute) {
            return '';
        }

        return ' Dev mode uses system PHP/nc for LAN access when the app is not listed under Local Network.';
    }

    /**
     * @param  iterable<int, BiometricDevice>  $devices
     * @return array<int, array<string, mixed>>
     */
    public function checkMany(iterable $devices): array
    {
        $results = [];

        foreach ($devices as $device) {
            $check = $this->check($device);
            $results[$device->id] = [
                ...$check,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return $results;
    }

    /**
     * @return array{ok: bool, latency_ms: ?int, error: ?string}
     */
    private function ping(string $ip): array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['ok' => false, 'latency_ms' => null, 'error' => 'Invalid IP address.'];
        }

        $started = microtime(true);

        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::timeout(5)->run(['ping', '-n', '1', '-w', '2000', $ip]);
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS: -W is milliseconds; -t is whole-command timeout (seconds).
            $pingBin = is_executable('/sbin/ping') ? '/sbin/ping' : 'ping';
            $result = Process::timeout(6)->run([$pingBin, '-c', '1', '-t', '3', $ip]);
        } else {
            $result = Process::timeout(5)->run(['ping', '-c', '1', '-W', '3', $ip]);
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if ($result->successful()) {
            return ['ok' => true, 'latency_ms' => $latencyMs, 'error' => null];
        }

        return [
            'ok' => false,
            'latency_ms' => $latencyMs,
            'error' => 'Ping failed.',
        ];
    }

    /**
     * @return array{ok: bool, latency_ms: ?int, error: ?string}
     */
    private function tcp(string $ip, int $port): array
    {
        if (NativePhpSystemPhp::shouldUseForDeviceIo()) {
            $viaNc = $this->tcpViaNc($ip, $port);
            if ($viaNc !== null) {
                return $viaNc;
            }
        }

        $timeout = (float) config('biometric.device.reachability_timeout_seconds', 5);
        $started = microtime(true);

        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            $socket = @stream_socket_client(
                sprintf('tcp://%s:%d', $ip, $port),
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
            );
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if ($socket === false) {
            $detail = $errstr !== '' ? $errstr : ('errno '.$errno);

            return [
                'ok' => false,
                'latency_ms' => $latencyMs,
                'error' => 'TCP: '.$detail,
                'errno' => $errno,
            ];
        }

        fclose($socket);

        return ['ok' => true, 'latency_ms' => $latencyMs, 'error' => null, 'errno' => null];
    }

    /**
     * @return array{ok: bool, latency_ms: ?int, error: ?string, errno: ?int}|null
     */
    private function tcpViaNc(string $ip, int $port): ?array
    {
        $nc = is_executable('/usr/bin/nc') ? '/usr/bin/nc' : (is_executable('/bin/nc') ? '/bin/nc' : null);
        if ($nc === null) {
            return null;
        }

        $timeout = max(1, (int) ceil((float) config('biometric.device.reachability_timeout_seconds', 5)));
        $started = microtime(true);

        $result = Process::timeout($timeout + 3)->run([
            $nc, '-z', '-G', (string) $timeout, $ip, (string) $port,
        ]);

        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if ($result->successful()) {
            return ['ok' => true, 'latency_ms' => $latencyMs, 'error' => null, 'errno' => null];
        }

        return [
            'ok' => false,
            'latency_ms' => $latencyMs,
            'error' => 'TCP: '.trim($result->errorOutput() ?: $result->output() ?: 'nc probe failed'),
            'errno' => null,
        ];
    }
}
