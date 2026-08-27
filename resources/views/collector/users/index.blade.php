@extends('collector.layout')

@section('title', config('app.name').' — Device users')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        @if ($selectedDevice)
            · Users on <strong>{{ $selectedDevice->name }}</strong> ({{ $selectedDevice->ip_address }}:{{ $selectedDevice->port }})
        @else
            · <span>Enrolled device users</span>
        @endif
    </p>

    <div class="card">
        <form method="get" action="{{ route('users.index') }}" class="data-table-toolbar" id="users-filter-form">
            <div class="field field-device">
                <label for="device_id">Device</label>
                <select id="device_id" name="device_id" onchange="this.form.submit()">
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
                >
                <button type="submit" class="secondary">Apply</button>
                @if ($selectedDeviceId || $search !== '')
                    <a href="{{ route('users.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
        @if ($selectedDevice)
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin: 0.75rem 0 0;">
                <a href="{{ route('users.create', ['device_id' => $selectedDevice->id]) }}" class="btn">Add user</a>
                <form method="post" action="{{ route('users.refresh', $selectedDevice) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="secondary" title="Read enrolled users from the device now">Refresh from device</button>
                </form>
            </div>
        @elseif ($devices->isNotEmpty())
            <p class="muted" style="margin: 0.75rem 0 0; font-size: 0.875rem;">
                Select a device to pull the latest user list from the biometric unit, or run <em>Collect now</em> on the dashboard.
            </p>
        @endif
    </div>

    <div class="card data-table-card">
        <div class="data-table-header">
            <h2 style="margin:0;">Enrolled users</h2>
            <span class="muted">{{ number_format($filteredTotal) }} total</span>
        </div>

        @if ($users->isEmpty())
            <p class="muted" style="padding: 1rem 1.25rem; margin: 0;">
                No users in the local database yet.
                @if ($selectedDevice)
                    Click <strong>Refresh from device</strong> while the device is online, or run collection from the dashboard.
                @else
                    Pick a device and refresh from the device, or wait for the next scheduled collect.
                @endif
            </p>
        @else
            <div class="data-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Card / RFID</th>
                            <th>Device UID</th>
                            <th>Device</th>
                            <th>IP address</th>
                            <th>Last synced</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="num">{{ $users->firstItem() + $loop->index }}</td>
                                <td><code>{{ $user->user_id }}</code></td>
                                <td>{{ $user->name ?? '—' }}</td>
                                <td>{{ \App\Support\ZkTecoUserPrivilege::label($user->privilege) }}</td>
                                <td>{{ $user->card_number ?? '—' }}</td>
                                <td>{{ $user->device_uid ?? '—' }}</td>
                                <td>{{ $user->device?->name ?? '—' }}</td>
                                <td><code>{{ $user->device?->ip_address ?? '—' }}</code></td>
                                <td>{{ \App\Support\CollectorDisplayTime::format($user->synced_at) }}</td>
                                <td>
                                    <a href="{{ route('users.edit', $user) }}" class="view-logs-link">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('collector.partials.data-table-pagination', [
                'paginator' => $users,
                'perPageOptions' => $perPageOptions,
                'perPageSelection' => $perPageSelection,
                'perPageFormAction' => route('users.index'),
            ])
        @endif
    </div>
@endsection
