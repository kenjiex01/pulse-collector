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
                            <th>Logs</th>
                            <th>Archived at</th>
                            <th>Modified</th>
                            <th>Size</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($archives as $archive)
                            <tr>
                                <td><code>{{ $archive['filename'] }}</code></td>
                                <td>{{ $archive['collector_name'] ?? '—' }}</td>
                                <td>{{ $archive['logs_count'] !== null ? number_format($archive['logs_count']) : '—' }}</td>
                                <td>{{ $archive['archived_at'] ? \App\Support\CollectorDisplayTime::format(\Carbon\Carbon::parse($archive['archived_at'])) : '—' }}</td>
                                <td>{{ $archive['modified_at'] }}</td>
                                <td>{{ number_format($archive['size_bytes'] / 1024, 1) }} KB</td>
                                <td>
                                    <a href="{{ route('archives.download', ['filename' => $archive['filename']]) }}" class="btn secondary">Download</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
