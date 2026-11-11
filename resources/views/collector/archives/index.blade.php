@extends('collector.layout')

@section('title', config('app.name').' — Deleted log backups')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        · <span>Deleted log backups</span>
    </p>

    <div class="card">
        <h2 style="margin-top:0;">Download backup JSON</h2>
        <p class="muted" style="margin-bottom:0.75rem;">
            When retention deletes old punches, the collector saves a plain JSON backup first under
            <code>{{ $archiveDirectory }}/</code>. Download those files here for safekeeping or transfer to another PC.
            <strong>Backup logs</strong> is what is inside each JSON file.
            <strong>Collector logs now</strong> is what this PC still has for that device and date range.
        </p>
        <p class="muted" id="archive-admin-hint" hidden style="margin-bottom:0.75rem;">
            Admin tools are on. Restore puts the backup back into this collector only. ZKTeco time clocks can be read or fully cleared; they cannot receive old punches again.
        </p>
        <p style="margin:0;">
            <a href="{{ route('archives.upload') }}" class="btn secondary">Upload backup JSON</a>
        </p>
    </div>

    <div class="card data-table-card">
        <div class="data-table-header">
            <h2 style="margin:0;">Backup files</h2>
            <span class="muted">{{ number_format(count($archives)) }} file(s)</span>
        </div>

        @if ($archives === [])
            <p class="muted" style="padding: 1rem 1.25rem; margin: 0;">
                No deleted-log backup files yet. They appear here after retention prunes punches older than your configured retention months.
            </p>
        @else
            <div class="data-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Collector</th>
                            <th>Backup logs</th>
                            <th>Collector logs now</th>
                            <th>Archived at</th>
                            <th>Size</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($archives as $archive)
                            @php
                                $fromDate = $archive['punched_from'] ? \Illuminate\Support\Str::before($archive['punched_from'], ' ') : null;
                                $toDate = $archive['punched_to'] ? \Illuminate\Support\Str::before($archive['punched_to'], ' ') : null;
                                $collectorUrl = ($archive['collector_device_id'] && $fromDate && $toDate)
                                    ? route('logs.index', [
                                        'device_id' => $archive['collector_device_id'],
                                        'date_from' => $fromDate,
                                        'date_to' => $toDate,
                                    ])
                                    : route('logs.index');
                            @endphp
                            <tr>
                                <td><code>{{ $archive['filename'] }}</code></td>
                                <td>{{ $archive['collector_name'] ?? '—' }}</td>
                                <td>
                                    <div>{{ $archive['logs_count'] !== null ? number_format($archive['logs_count']) : '—' }} punch(es)</div>
                                    <div class="muted" style="font-size:0.8rem;">
                                        {{ $archive['device_name'] ?? 'Unknown device' }}
                                        @if ($archive['device_ip'])
                                            · {{ $archive['device_ip'] }}
                                        @endif
                                    </div>
                                    <div class="muted" style="font-size:0.8rem;">
                                        {{ $archive['punched_from'] ?? '—' }} → {{ $archive['punched_to'] ?? '—' }}
                                    </div>
                                    <a href="{{ route('archives.backup-logs', ['filename' => $archive['filename']]) }}">View backup logs</a>
                                </td>
                                <td>
                                    <div>{{ number_format($archive['collector_logs_count']) }} punch(es) in this range</div>
                                    <a href="{{ $collectorUrl }}">View collector logs</a>
                                </td>
                                <td>{{ $archive['archived_at'] ? \App\Support\CollectorDisplayTime::format(\Carbon\Carbon::parse($archive['archived_at'])) : '—' }}</td>
                                <td>{{ number_format($archive['size_bytes'] / 1024, 1) }} KB</td>
                                <td>
                                    <a href="{{ route('archives.download', ['filename' => $archive['filename']]) }}" class="btn secondary" data-file-download>Download</a>
                                    <form method="post" action="{{ route('archives.restore', ['filename' => $archive['filename']]) }}" data-archive-admin hidden style="margin-top:0.4rem;" onsubmit="return confirm('Put these punches back into this collector? The time clock cannot accept old punches.');">
                                        @csrf
                                        <button type="submit" class="btn">Restore into collector</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        (function () {
            let buffer = '';

            document.addEventListener('keydown', function (event) {
                if (event.metaKey || event.ctrlKey || event.altKey || event.key.length !== 1) {
                    return;
                }

                const target = event.target;
                if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
                    return;
                }

                buffer = (buffer + event.key).slice(-20);

                if (!buffer.endsWith('.admin')) {
                    return;
                }

                document.querySelectorAll('[data-archive-admin]').forEach(function (node) {
                    node.hidden = false;
                });

                const hint = document.getElementById('archive-admin-hint');
                if (hint) {
                    hint.hidden = false;
                }
            });
        })();
    </script>
@endsection
