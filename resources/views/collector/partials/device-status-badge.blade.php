@if ($status)
    @php
        $label = $status['status_label'] ?? 'offline';
        $class = match ($label) {
            'online' => 'ok',
            'port closed' => 'port',
            'disabled' => 'disabled',
            default => 'offline',
        };
        $text = match ($label) {
            'online' => 'online'.(isset($status['latency_ms']) ? ' ('.$status['latency_ms'].' ms)' : ''),
            'port closed' => 'port closed',
            'disabled' => 'disabled',
            default => 'offline',
        };
        $title = $status['message'] ?? match ($label) {
            'online' => 'TCP port open — ready for ZkTeco collection.',
            'port closed' => 'Ping works but TCP port is not open.',
            default => 'Host and port are not reachable.',
        };
    @endphp
    <span class="badge {{ $class }} device-status-badge" title="{{ $title }}">{{ $text }}</span>
@else
    <span class="badge checking device-status-badge">checking…</span>
@endif
