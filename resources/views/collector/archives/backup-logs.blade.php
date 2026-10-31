@extends('collector.layout')

@section('title', config('app.name').' — Backup logs')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('archives.index') }}">← Deleted log backups</a>
        · <span>{{ $filename }}</span>
    </p>

    <div class="card">
        <h2 style="margin-top:0;">Logs inside this backup</h2>
        <p class="muted" style="margin-bottom:0;">
            {{ number_format($logs->total()) }} punch(es)
            @if (! empty($payload['collector_name']))
                · {{ $payload['collector_name'] }}
            @endif
            @if (! empty($payload['reason']))
                · {{ $payload['reason'] }}
            @endif
        </p>
    </div>

    <div class="card data-table-card">
        <div class="data-table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Punched at</th>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Device</th>
                        <th>IP</th>
                        <th>State</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log['punched_at'] ?? '—' }}</td>
                            <td>{{ $log['user_id'] ?? '—' }}</td>
                            <td>{{ $log['user_name'] ?? '—' }}</td>
                            <td>{{ $log['device_name'] ?? '—' }}</td>
                            <td>{{ $log['device_ip'] ?? '—' }}</td>
                            <td>{{ $log['punch_state'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">This backup file has no punch rows.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="data-table-footer">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
