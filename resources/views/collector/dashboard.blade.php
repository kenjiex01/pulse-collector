@extends('collector.layout')

@section('title', config('app.name').' — Dashboard')

@section('content')
    <p class="muted">Pulse — runs every {{ $collectIntervalMinutes }} minute(s). New logs upload to S3 as gzipped JSON.</p>

    <div class="card">
        <h2>Collector name (S3 source)</h2>
        <p class="muted">Used as the biometric name in the S3 path and export filename.</p>
        <form method="post" action="{{ route('settings.collector-name') }}">
            @csrf
            <label for="collector_name">Name</label>
            <div class="field-row">
                <input
                    type="text"
                    id="collector_name"
                    name="collector_name"
                    class="field"
                    value="{{ old('collector_name', $collectorName) }}"
                    placeholder="e.g. Cainta Campus — Front Desk PC"
                    maxlength="128"
                    required
                >
                <button type="submit" class="secondary">Save name</button>
            </div>
        </form>
        <p class="muted" style="margin-top: 0.75rem;">Biometric / S3 folder name: <code>{{ $collectorSlug }}</code></p>
    </div>

    <div class="card">
        <h2>Attendance retrieval window</h2>
        <p class="muted">Use a start date to skip old punches from the device during new collections.</p>
        <form method="post" action="{{ route('settings.attendance-start-date') }}">
            @csrf
            <label for="attendance_start_date">Start date</label>
            <div class="field-row">
                <input
                    type="date"
                    id="attendance_start_date"
                    name="attendance_start_date"
                    class="field"
                    value="{{ old('attendance_start_date', $attendanceStartDate) }}"
                    max="{{ now()->toDateString() }}"
                >
                <button type="submit" class="secondary">Save start date</button>
            </div>
        </form>
        <p class="muted" style="margin-top: 0.75rem;">
            @if ($attendanceStartDate)
                Collections will keep punches on or after <strong>{{ $attendanceStartDate }}</strong>.
            @else
                No start date set. The collector will include all punches returned by the device.
            @endif
        </p>
    </div>

    <div class="card">
        <h2>Log retention</h2>
        <p class="muted">Keep collected punches from the last N months (from today). Default is <strong>2 months</strong>. Save only stores the setting. Older rows are archived as JSON and removed on the next Collect now / auto-collect.</p>
        <form method="post" action="{{ route('settings.log-retention') }}">
            @csrf
            <label for="log_retention_months">Months to keep</label>
            <div class="field-row">
                <input
                    type="number"
                    id="log_retention_months"
                    name="log_retention_months"
                    class="field field-sm"
                    value="{{ old('log_retention_months', $logRetentionMonths) }}"
                    min="{{ $logRetentionMinMonths }}"
                    max="{{ $logRetentionMaxMonths }}"
                    step="1"
                    placeholder="e.g. 2"
                >
                <button type="submit" class="secondary">Save retention</button>
            </div>
            @error('log_retention_months')
                <p class="errors">{{ $message }}</p>
            @enderror
        </form>
        <p class="muted" style="margin-top: 0.75rem;">
            @if ($logRetentionMonths)
                Logs punched before <strong>{{ now()->subMonthsNoOverflow($logRetentionMonths)->toDateString() }}</strong>
                will be archived, then deleted on the next collect. Leave the field empty and save to keep all logs (collect stays blocked).
            @else
                No retention set. Manual Collect now and automatic collection are blocked until you enter a month window.
            @endif
        </p>
        <p class="muted" style="margin-top: 0.5rem; font-size: 0.8125rem;">
            Deleted-log JSON folder: <code>{{ $logRetentionArchiveDir }}</code>
        </p>
    </div>

    <div class="card">
        <h2>Collection schedule</h2>
        <p class="muted" style="margin:0;">
            Pulls logs from devices automatically every <strong>{{ $collectIntervalMinutes }}</strong> minute(s) while this desktop app is open
            (background worker). Manual <strong>Collect now</strong> also runs in the background so the dashboard stays usable.
            New punches upload to S3 as <code>.json.gzip</code> files.
        </p>
        <p class="muted" style="margin:0.65rem 0 0; font-size:0.875rem;">
            The auto-collect worker starts when the app opens. Change the interval with
            <code>BIOMETRIC_COLLECT_INTERVAL_MINUTES</code> in <code>.env</code>, then restart the app.
        </p>
        <form method="post" action="{{ route('collect.now') }}" style="margin-top: 1rem;" data-manual-collect-form data-collect-status-url="{{ route('collect.status') }}" data-no-loader>
            @csrf
            <button type="submit" data-manual-collect-submit>Collect now (manual)</button>
        </form>

        <div
            class="collect-progress"
            data-manual-collect-progress
            hidden
            aria-live="polite"
        >
            <div class="collect-progress-label">
                <span data-manual-collect-progress-label>Collecting…</span>
                <strong data-manual-collect-progress-pct></strong>
            </div>
            <div class="collect-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-manual-collect-progress-bar>
                <div class="collect-progress-fill is-indeterminate" data-manual-collect-progress-fill></div>
            </div>
            <p class="muted collect-progress-meta" data-manual-collect-progress-meta>Talking to devices and checking for S3 uploads…</p>
        </div>
    </div>

    <div class="card">
        <h2>S3 export</h2>
        <p style="margin:0;">
            @if ($s3Configured)
                <span class="badge ok">Configured</span>
                <span class="muted">
                    Gzipped JSON →
                    <code>biometric_logs/{{ now()->format('Y') }}/{{ now()->format('m') }}/{{ $collectorSlug }}/{{ $collectorSlug }}_YYYYMMDDHHMMSS.json.gzip</code>
                </span>
            @else
                <span class="badge warn">Not configured</span>
                <span class="muted">Exports stay in <code>storage/app/biometric-exports/</code>. Set <code>DB_BACKUP_S3_*</code> in <code>.env</code>.</span>
            @endif
        </p>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
            <h2 style="margin:0;">Devices</h2>
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <span class="muted" id="device-status-updated" style="font-size:0.8rem;">Checking connectivity…</span>
                <a href="{{ route('devices.create') }}" class="btn">Add device</a>
            </div>
        </div>
        <p class="muted" style="margin-top:0;">
            <strong>Online</strong> = TCP connect to the device port (ZkTeco default <code>4370</code>) — required to collect logs.
            Ping alone is not enough; if ping works but status is <em>port closed</em>, open comm/network on the device.
            Click a <strong>device name</strong> or <strong>Logs</strong> to view punches for that device only.
        </p>
        @if (config('nativephp-internal.running'))
            <p class="muted" style="margin-top:0.5rem;">
                <strong>macOS dev:</strong> If Pulse is not in System Settings → Local Network, this build uses your Mac’s
                <code>php</code> / <code>nc</code> (same as Terminal) to reach ZkTeco devices.
            </p>
        @endif
        @if ($devices->isEmpty())
            <p class="muted">No devices yet. Click <strong>Add device</strong> and enter the ZkTeco IP (e.g. K30, port 4370).</p>
        @else
            <div class="devices-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Model</th>
                        <th>IP address</th>
                        <th>Last collect</th>
                        <th>Connectivity</th>
                        <th>Device storage (attendance)</th>
                        <th>Last collect error</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <td>
                                <a
                                    href="{{ route('logs.index', ['device_id' => $device->id]) }}"
                                    class="device-name-link"
                                    title="View collected logs for {{ $device->name }}"
                                >{{ $device->name }}</a>
                            </td>
                            <td>{{ $device->model }}</td>
                            <td><code>{{ $device->ip_address }}:{{ $device->port }}</code></td>
                            <td class="device-last-collect-cell" data-device-id="{{ $device->id }}">
                                {{ \App\Support\CollectorDisplayTime::format($device->last_collected_at) }}
                            </td>
                            <td
                                class="device-status-cell"
                                data-device-id="{{ $device->id }}"
                            >
                                @include('collector.partials.device-status-badge', ['status' => $deviceStatuses[$device->id] ?? null])
                            </td>
                            <td
                                class="device-storage-cell muted"
                                data-device-id="{{ $device->id }}"
                                style="font-size: 0.8125rem;"
                            >
                                —
                            </td>
                            <td
                                class="device-collect-error-cell"
                                data-device-id="{{ $device->id }}"
                            >
                                @include('collector.partials.device-collect-error', ['error' => $device->last_error])
                            </td>
                            <td class="actions-cell">
                                <a
                                    href="{{ route('devices.edit', $device) }}"
                                    class="view-logs-link"
                                    title="Edit {{ $device->name }} IP and settings"
                                >
                                    Edit
                                </a>
                                <a
                                    href="{{ route('users.index', ['device_id' => $device->id]) }}"
                                    class="view-logs-link"
                                    title="View enrolled users on {{ $device->name }}"
                                >
                                    Users
                                </a>
                                <a
                                    href="{{ route('logs.index', ['device_id' => $device->id]) }}"
                                    class="view-logs-link"
                                    title="View logs for {{ $device->name }}"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    Logs
                                </a>
                                <form method="post" action="{{ route('devices.destroy', $device) }}" onsubmit="return confirm('Remove this device?');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Recent S3 exports</h2>
        <p class="muted" style="margin: 0 0 0.75rem; font-size: 0.8125rem;">Times in {{ \App\Support\CollectorDisplayTime::timezoneLabel() }}.</p>
        @if ($recentExports->isEmpty())
            <p class="muted">No exports yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Campus</th>
                        <th>Device</th>
                        <th>Kind</th>
                        <th>Logs</th>
                        <th>Users</th>
                        <th>S3 key</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentExports as $batch)
                        <tr>
                            <td>{{ \App\Support\CollectorDisplayTime::format($batch->created_at) }}</td>
                            <td>{{ $batch->campus?->code }}</td>
                            <td>{{ $batch->device?->name }}</td>
                            <td>{{ $batch->batch_kind ?? 'attendance' }}</td>
                            <td>{{ $batch->logs_count }}</td>
                            <td>{{ $batch->users_count ?? 0 }}</td>
                            <td>{{ $batch->s3_key ?? ($batch->error_message ?: $batch->sql_filename) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (! $devices->isEmpty())
        <script>
            (function () {
                const pollSeconds = {{ (int) $statusPollSeconds }};
                const statusUrl = @json(route('devices.status'));
                const updatedEl = document.getElementById('device-status-updated');

                function renderBadge(device) {
                    if (device.disabled) {
                        return { className: 'badge disabled device-status-badge', label: 'disabled', title: 'Excluded from scheduled collection.' };
                    }
                    if (device.online) {
                        const ms = device.latency_ms != null ? ' (' + device.latency_ms + ' ms)' : '';
                        return { className: 'badge ok device-status-badge', label: 'online' + ms, title: 'TCP port open — ready for ZkTeco collection.' };
                    }
                    if (device.status_label === 'port closed' || (device.ping_ok && !device.tcp_ok)) {
                        return {
                            className: 'badge port device-status-badge',
                            label: 'port closed',
                            title: device.message || 'Ping works but TCP port is not open. Check ZkTeco network settings and port 4370.',
                        };
                    }
                    return {
                        className: 'badge offline device-status-badge',
                        label: 'offline',
                        title: device.message || 'Host and port are not reachable.',
                    };
                }

                function formatCollectedAt(iso) {
                    if (!iso) return '—';
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return '—';
                    const pad = n => String(n).padStart(2, '0');
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                }

                function formatNumber(value) {
                    if (value == null || Number.isNaN(Number(value))) {
                        return null;
                    }
                    return Number(value).toLocaleString();
                }

                function renderStorage(device) {
                    const span = document.createElement('span');
                    span.className = 'device-storage-label';
                    const storage = device.storage;

                    if (!storage || !storage.available) {
                        span.textContent = '—';
                        span.title = storage?.message || device.message || 'Storage stats load when the device is online.';
                        return span;
                    }

                    const used = formatNumber(storage.attendance_used);
                    const cap = formatNumber(storage.attendance_capacity);
                    const free = formatNumber(storage.attendance_free);

                    if (cap != null && free != null) {
                        span.textContent = used + ' / ' + cap + ' (' + free + ' free)';
                        span.title = 'Attendance logs on device: used / capacity (free slots).';
                    } else if (used != null) {
                        span.textContent = used + ' on device';
                        span.title = storage.message || 'Used count only; capacity not reported by this firmware.';
                    } else {
                        span.textContent = '—';
                        span.title = storage.message || '';
                    }

                    return span;
                }

                function renderCollectError(error) {
                    if (error) {
                        const span = document.createElement('span');
                        span.className = 'device-collect-error-msg';
                        span.title = error;
                        span.textContent = error;
                        return span;
                    }
                    const span = document.createElement('span');
                    span.className = 'device-collect-error-none';
                    span.textContent = '—';
                    return span;
                }

                function applyStatuses(payload) {
                    const map = Object.fromEntries((payload.devices || []).map(d => [String(d.id), d]));
                    document.querySelectorAll('.device-status-cell').forEach(cell => {
                        const id = cell.getAttribute('data-device-id');
                        const device = map[id];
                        if (!device) return;
                        const badge = renderBadge(device);
                        const badgeEl = cell.querySelector('.device-status-badge');
                        if (badgeEl) {
                            badgeEl.className = badge.className;
                            badgeEl.textContent = badge.label;
                            badgeEl.title = badge.title;
                        }
                    });
                    document.querySelectorAll('.device-storage-cell').forEach(cell => {
                        const id = cell.getAttribute('data-device-id');
                        const device = map[id];
                        if (!device) return;
                        cell.replaceChildren(renderStorage(device));
                    });
                    document.querySelectorAll('.device-collect-error-cell').forEach(cell => {
                        const id = cell.getAttribute('data-device-id');
                        const device = map[id];
                        if (!device) return;
                        cell.replaceChildren(renderCollectError(device.last_error || null));
                    });
                    document.querySelectorAll('.device-last-collect-cell').forEach(cell => {
                        const id = cell.getAttribute('data-device-id');
                        const device = map[id];
                        if (!device) return;
                        cell.textContent = formatCollectedAt(device.last_collected_at);
                    });
                    if (updatedEl && payload.checked_at) {
                        const t = new Date(payload.checked_at);
                        updatedEl.textContent = 'Last checked: ' + t.toLocaleTimeString();
                    }
                }

                async function refreshStatuses() {
                    if (window.__biometricCollecting) {
                        return;
                    }
                    try {
                        const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        applyStatuses(await res.json());
                    } catch (e) {
                        if (updatedEl) updatedEl.textContent = 'Status check failed — retrying…';
                    }
                }

                refreshStatuses();
                setInterval(refreshStatuses, Math.max(5, pollSeconds) * 1000);
            })();
        </script>
    @endif

    <script>
        (function () {
            const form = document.querySelector('[data-manual-collect-form]');
            if (! form) {
                return;
            }

            const submitBtn = form.querySelector('[data-manual-collect-submit]');
            const progressBox = document.querySelector('[data-manual-collect-progress]');
            const labelEl = document.querySelector('[data-manual-collect-progress-label]');
            const pctEl = document.querySelector('[data-manual-collect-progress-pct]');
            const fillEl = document.querySelector('[data-manual-collect-progress-fill]');
            const barEl = document.querySelector('[data-manual-collect-progress-bar]');
            const metaEl = document.querySelector('[data-manual-collect-progress-meta]');

            let fakeTimer = null;

            const setProgress = ({ label, percent = null, meta = '', indeterminate = false } = {}) => {
                if (! progressBox) {
                    return;
                }
                progressBox.hidden = false;
                if (labelEl && label) {
                    labelEl.textContent = label;
                }
                if (metaEl) {
                    metaEl.textContent = meta;
                }
                if (indeterminate || percent === null) {
                    if (pctEl) {
                        pctEl.textContent = '';
                    }
                    if (fillEl) {
                        fillEl.classList.add('is-indeterminate');
                        fillEl.style.width = '40%';
                    }
                    if (barEl) {
                        barEl.removeAttribute('aria-valuenow');
                    }
                    return;
                }

                const clamped = Math.max(0, Math.min(100, Math.round(percent)));
                if (pctEl) {
                    pctEl.textContent = `${clamped}%`;
                }
                if (fillEl) {
                    fillEl.classList.remove('is-indeterminate');
                    fillEl.style.width = `${clamped}%`;
                }
                if (barEl) {
                    barEl.setAttribute('aria-valuenow', String(clamped));
                }
            };

            const stopFakeProgress = () => {
                if (fakeTimer) {
                    window.clearInterval(fakeTimer);
                    fakeTimer = null;
                }
            };

            const startFakeProgress = () => {
                stopFakeProgress();
                let value = 8;
                const stages = [
                    { at: 25, label: 'Connecting to devices…', meta: 'Checking device TCP / ZkTeco session' },
                    { at: 55, label: 'Collecting attendance logs…', meta: 'Reading punches and users from devices' },
                    { at: 80, label: 'Preparing S3 export…', meta: 'Uploads only if there are new/pending logs' },
                    { at: 92, label: 'Finishing collection…', meta: 'Almost done' },
                ];
                setProgress({
                    label: 'Starting manual collect…',
                    percent: value,
                    meta: 'Please wait — do not close the app',
                    indeterminate: false,
                });
                fakeTimer = window.setInterval(() => {
                    if (value < 92) {
                        value += value < 50 ? 3 : 1.5;
                    }
                    const stage = [...stages].reverse().find((s) => value >= s.at) || stages[0];
                    setProgress({
                        label: stage.label,
                        percent: Math.min(92, value),
                        meta: stage.meta,
                    });
                }, 400);
            };

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (submitBtn?.disabled) {
                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Collecting…';
                }

                startFakeProgress();
                window.__biometricCollecting = true;
                window.CollectorLoader?.show(
                    'Collecting logs from devices… This can take several minutes.',
                    { sticky: true }
                );

                const statusUrl = form.getAttribute('data-collect-status-url');
                let keepLoader = false;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                        },
                        credentials: 'same-origin',
                        body: new FormData(form),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        throw new Error(friendlyCollectError(payload.message, response.status));
                    }

                    if (payload.started && statusUrl) {
                        const finished = await pollCollectStatus(statusUrl);
                        stopFakeProgress();
                        keepLoader = finishCollect(finished);
                        return;
                    }

                    stopFakeProgress();
                    keepLoader = finishCollect(payload);
                } catch (error) {
                    stopFakeProgress();
                    setProgress({
                        label: 'Collect failed',
                        percent: 0,
                        meta: error?.message || 'Unable to run manual collect.',
                    });
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Collect now (manual)';
                    }
                    window.alert(error?.message || 'Unable to run manual collect.');
                } finally {
                    window.__biometricCollecting = false;
                    if (! keepLoader) {
                        window.CollectorLoader?.hide({ sticky: true });
                    }
                }
            });

            async function pollCollectStatus(statusUrl) {
                const startedAt = Date.now();
                const timeoutMs = 15 * 60 * 1000;

                while (Date.now() - startedAt < timeoutMs) {
                    await new Promise((resolve) => window.setTimeout(resolve, 1500));

                    const response = await fetch(statusUrl, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                        credentials: 'same-origin',
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (payload.done) {
                        return payload;
                    }

                    if (payload.message && metaEl) {
                        metaEl.textContent = payload.message;
                        window.CollectorLoader?.show(payload.message, { sticky: true });
                    }
                }

                throw new Error('Collection is still running after 15 minutes. Check Last collect error, then try again.');
            }

            function finishCollect(payload) {
                const summary = payload.summary || {};
                const crashed = payload.state === 'failed';

                setProgress({
                    label: crashed || payload.ok === false ? 'Collect finished with errors' : 'Collect complete',
                    percent: 100,
                    meta: payload.message || sprintfFallback(summary),
                });

                if (crashed) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Collect now (manual)';
                    }
                    window.alert(payload.message || 'Collection failed.');
                    return false;
                }

                window.CollectorLoader?.show('Reloading dashboard…', { sticky: true });
                window.setTimeout(() => {
                    window.location.reload();
                }, 900);

                return true;
            }

            function friendlyCollectError(message, status) {
                const text = String(message || '').trim();
                if (text && text.toLowerCase() !== 'server error') {
                    return text;
                }

                return `Collect failed (HTTP ${status}). The first pull from a busy device can take a few minutes — wait, then try Collect now again.`;
            }

            function sprintfFallback(summary) {
                const logs = Number(summary.logs_inserted || 0);
                const uploads = Number(summary.batches_uploaded || 0);
                return `Collection finished: ${logs} new log(s), ${uploads} S3 upload(s).`;
            }
        })();
    </script>
@endsection
