@extends('collector.layout')

@section('title', config('app.name').' — Download DTR')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        · <span>Download DTR</span>
    </p>

    <div class="card">
        <h2 style="margin-top:0;">Download DTR</h2>
        <p class="muted">
            Pick a punch date range and select one or more enrolled users. The export is a CSV timesheet
            (<code>Employee: Name (user id)</code> blocks with Date / In / Out columns) compatible with Cainta-style DTR uploads.
        </p>

        <form method="post" action="{{ route('dtr.download') }}" id="dtr-download-form" style="margin-top:1rem;">
            @csrf

            <div class="logs-filter-grid" style="margin-bottom:1rem;">
                <div class="logs-filter-daterange">
                    <span class="logs-filter-daterange-legend">Punch date range</span>
                    <div class="logs-filter-daterange-inputs">
                        <div class="field-date-inline">
                            <label for="date_from">From</label>
                            <input type="date" id="date_from" name="date_from" value="{{ old('date_from', $dateFrom) }}" required>
                        </div>
                        <span class="logs-filter-daterange-sep" aria-hidden="true">to</span>
                        <div class="field-date-inline">
                            <label for="date_to">To</label>
                            <input type="date" id="date_to" name="date_to" value="{{ old('date_to', $dateTo) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="data-table-toolbar" style="margin-bottom:0.75rem;">
                <div class="field field-device">
                    <label for="device_id">Filter users by device</label>
                    <select id="device_id" name="device_id" form="dtr-user-filter-form">
                        <option value="">All devices</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected($selectedDeviceId === $device->id)>
                                {{ $device->name }} ({{ $device->ip_address }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="data-table-toolbar-actions">
                    <label for="search" class="toolbar-inline-label">Search</label>
                    <input
                        type="search"
                        id="search"
                        name="search"
                        class="toolbar-search-input"
                        value="{{ $search }}"
                        placeholder="User ID, name, or card"
                        form="dtr-user-filter-form"
                    >
                    <button type="submit" class="secondary" form="dtr-user-filter-form">Apply filter</button>
                    @if ($selectedDeviceId || $search !== '')
                        <a href="{{ route('dtr.index', ['date_from' => old('date_from', $dateFrom), 'date_to' => old('date_to', $dateTo)]) }}" class="btn secondary">Reset filter</a>
                    @endif
                </div>
            </div>

            <div class="card data-table-card" style="margin-bottom:1rem;">
                <div class="data-table-header">
                    <div>
                        <h3 style="margin:0;">Select users</h3>
                        <p class="muted" style="margin:0.35rem 0 0; font-size:0.875rem;">
                            {{ number_format($users->count()) }} user(s) shown
                        </p>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                        <button type="button" class="secondary" data-dtr-select-all>Select all shown</button>
                        <button type="button" class="secondary" data-dtr-clear-all>Clear selection</button>
                    </div>
                </div>

                @if ($users->isEmpty())
                    <p class="muted">
                        No enrolled users match the current filter.
                        @if ($selectedDeviceId)
                            Try another device or refresh users from the device.
                        @else
                            Add devices and sync users first from the dashboard or Device users page.
                        @endif
                    </p>
                @else
                    <div class="data-table-scroll" style="max-height:min(50vh, 420px);">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem;"></th>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Device</th>
                                    <th>Campus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    @php
                                        $userKey = \App\Services\DtrExportService::userKey((int) $user->biometric_device_id, (string) $user->user_id);
                                    @endphp
                                    <tr>
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="users[]"
                                                value="{{ $userKey }}"
                                                @checked(in_array($userKey, $selectedUserKeys, true))
                                                data-dtr-user-checkbox
                                            >
                                        </td>
                                        <td><code>{{ $user->user_id }}</code></td>
                                        <td>{{ $user->name ?? '—' }}</td>
                                        <td>{{ $user->device?->name ?? '—' }}</td>
                                        <td>{{ $user->device?->campus?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <button type="submit" class="btn" @disabled($users->isEmpty())>Download DTR (CSV)</button>
                <a href="{{ route('logs.index') }}" class="btn secondary">View collected logs</a>
            </div>
        </form>

        <form method="get" action="{{ route('dtr.index') }}" id="dtr-user-filter-form" style="display:none;">
            <input type="hidden" name="date_from" value="{{ old('date_from', $dateFrom) }}">
            <input type="hidden" name="date_to" value="{{ old('date_to', $dateTo) }}">
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('dtr-download-form');
            const filterForm = document.getElementById('dtr-user-filter-form');

            if (filterForm) {
                filterForm.addEventListener('submit', () => {
                    const mainFrom = document.getElementById('date_from');
                    const mainTo = document.getElementById('date_to');
                    const hiddenFrom = filterForm.querySelector('[name="date_from"]');
                    const hiddenTo = filterForm.querySelector('[name="date_to"]');

                    if (mainFrom && hiddenFrom) {
                        hiddenFrom.value = mainFrom.value;
                    }

                    if (mainTo && hiddenTo) {
                        hiddenTo.value = mainTo.value;
                    }
                });
            }

            if (! form) {
                return;
            }

            const checkboxes = () => Array.from(form.querySelectorAll('[data-dtr-user-checkbox]'));

            form.querySelector('[data-dtr-select-all]')?.addEventListener('click', () => {
                checkboxes().forEach((checkbox) => {
                    checkbox.checked = true;
                });
            });

            form.querySelector('[data-dtr-clear-all]')?.addEventListener('click', () => {
                checkboxes().forEach((checkbox) => {
                    checkbox.checked = false;
                });
            });

            form.addEventListener('submit', (event) => {
                const selected = checkboxes().some((checkbox) => checkbox.checked);

                if (! selected) {
                    event.preventDefault();
                    window.alert('Select at least one user to download.');
                }
            });
        })();
    </script>
@endsection
