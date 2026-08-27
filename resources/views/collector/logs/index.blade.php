@extends('collector.layout')

@section('title', config('app.name').' — Collected logs')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        @if ($selectedDevice)
            · Logs for <strong>{{ $selectedDevice->name }}</strong> ({{ $selectedDevice->ip_address }}:{{ $selectedDevice->port }})
        @else
            · <span>Collected logs</span>
        @endif
    </p>

    <div class="card">
        <form method="get" action="{{ route('logs.index') }}" class="logs-filter-form">
            <div class="logs-filter-grid">
                <div class="field field-device">
                    <label for="device_id">Device</label>
                    <select id="device_id" name="device_id">
                        <option value="">All devices</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected($selectedDeviceId === $device->id)>
                                {{ $device->name }} ({{ $device->ip_address }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="logs-filter-daterange">
                    <span class="logs-filter-daterange-legend">Punch date range</span>
                    <div class="logs-filter-daterange-inputs">
                        <div class="field-date-inline">
                            <label for="date_from">From</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $dateFrom ?? '' }}">
                        </div>
                        <span class="logs-filter-daterange-sep" aria-hidden="true">to</span>
                        <div class="field-date-inline">
                            <label for="date_to">To</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $dateTo ?? '' }}">
                        </div>
                    </div>
                </div>

                <div class="logs-filter-search-block">
                    <div class="logs-filter-search-row">
                        <div class="field-search">
                            <label for="search">Search</label>
                            <input
                                type="search"
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="User ID, name, or card"
                            >
                        </div>
                        <div class="logs-filter-search-actions">
                            <button type="submit" class="secondary">Apply filters</button>
                            @if ($selectedDeviceId || $search !== '' || ($dateFrom ?? '') !== '' || ($dateTo ?? '') !== '')
                                <a href="{{ route('logs.index') }}" class="btn secondary">Reset</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card data-table-card">
        <div class="data-table-header">
            <h2 style="margin:0;">Collected logs</h2>
            <span class="muted">{{ number_format($filteredTotal) }} total</span>
        </div>

        @if ($logs->isEmpty())
            <p class="muted" style="padding: 0.5rem 0;">No logs match your filters. Add a device and run <em>Collect now</em>, or wait for the schedule.</p>
        @else
            <div class="data-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Punch time', 'sort' => 'punched_at', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'User ID', 'sort' => 'user_id', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Name', 'sort' => 'user_name', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Card / RFID', 'sort' => 'card_number', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Device', 'sort' => 'device', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'IP address', 'sort' => 'ip', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Verify', 'sort' => 'verify_mode', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'State', 'sort' => 'punch_state', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'S3', 'sort' => 's3', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                            <th>@include('collector.partials.sortable-th', ['label' => 'Collected at', 'sort' => 'created_at', 'currentSort' => $sortColumn, 'currentDirection' => $sortDirection])</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="num">{{ $logs->firstItem() + $loop->index }}</td>
                                <td>{{ $log->punched_at?->format('Y-m-d H:i:s') }}</td>
                                <td><code>{{ $log->user_id }}</code></td>
                                <td>{{ $log->user_name ?? '—' }}</td>
                                <td>{{ $log->card_number ?? '—' }}</td>
                                <td>{{ $log->device?->name ?? '—' }}</td>
                                <td><code>{{ $log->device?->ip_address ?? '—' }}</code></td>
                                <td>{{ $log->verify_mode ?? '—' }}</td>
                                <td>{{ $log->punch_state ?? '—' }}</td>
                                <td>
                                    @if ($log->s3_pushed_at)
                                        <span class="badge ok" title="Pushed {{ \App\Support\CollectorDisplayTime::format($log->s3_pushed_at) }}">Pushed</span>
                                    @else
                                        <span class="badge warn" title="Waiting for S3 upload">Pending</span>
                                    @endif
                                </td>
                                <td>{{ \App\Support\CollectorDisplayTime::format($log->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('collector.partials.data-table-pagination', [
                'paginator' => $logs,
                'perPageOptions' => $perPageOptions,
                'perPageSelection' => $perPageSelection,
                'perPageFormAction' => route('logs.index'),
            ])
        @endif
    </div>
@endsection
