{{-- Full-screen blocker while an update downloads / installs. App is unusable until restart. --}}
@php
    $updater = $desktopUpdater ?? ['enabled' => false];
    $updaterDownloading = is_array($updater['downloading'] ?? null) ? $updater['downloading'] : null;
    $updaterInstalling = is_array($updater['installing'] ?? null) ? $updater['installing'] : null;
    $updaterPending = is_array($updater['pending'] ?? null) ? $updater['pending'] : null;
    $updaterBlocking = ! empty($updater['enabled']) && ($updaterDownloading || $updaterInstalling || (! empty($updater['force_install']) && $updaterPending));
    $initialPercent = 0;
    $initialPhase = 'Downloading update';
    $initialVersion = '';
    if ($updaterInstalling) {
        $initialPercent = 100;
        $initialPhase = 'Installing update — app will reopen…';
        $initialVersion = (string) ($updaterInstalling['version'] ?? '');
    } elseif ($updaterDownloading) {
        $initialPercent = (int) round((float) ($updaterDownloading['percent'] ?? 0));
        $initialPhase = 'Downloading update';
        $initialVersion = (string) ($updaterDownloading['version'] ?? '');
    } elseif ($updaterPending) {
        $initialPercent = 100;
        $initialPhase = 'Installing update — app will reopen…';
        $initialVersion = (string) ($updaterPending['version'] ?? '');
    }
@endphp

@if (! empty($updater['enabled']))
    <style>
        .desktop-updater-overlay {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.8);
            padding: 1rem;
        }
        .desktop-updater-overlay.is-hidden { display: none !important; }
        .desktop-updater-card {
            width: 100%;
            max-width: 26rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem 1.75rem;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
            text-align: center;
        }
        .desktop-updater-brand {
            margin: 0;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #0B318F;
        }
        .desktop-updater-card h2 { margin: 0.75rem 0 0; font-size: 1.25rem; }
        .desktop-updater-card p { margin: 0.5rem 0 0; font-size: 0.9rem; line-height: 1.45; color: #475569; }
        .desktop-updater-phase { margin-top: 1.5rem !important; font-weight: 600; color: #0f172a !important; }
        .desktop-updater-percent {
            margin: 1.25rem 0 0 !important;
            font-size: 3rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: #0B318F;
            font-variant-numeric: tabular-nums;
        }
        .desktop-updater-track {
            margin-top: 1rem;
            height: 0.75rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .desktop-updater-fill {
            height: 100%;
            border-radius: inherit;
            background: #0B318F;
            transition: width 0.3s ease-out;
        }
        .desktop-updater-note { margin-top: 1.25rem !important; font-size: 0.75rem !important; }
    </style>
    <div
        id="desktop-updater-overlay"
        class="desktop-updater-overlay{{ $updaterBlocking ? '' : ' is-hidden' }}"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="desktop-updater-title"
        aria-describedby="desktop-updater-desc"
        data-status-url="{{ route('desktop.updater.status') }}"
        data-check-url="{{ route('desktop.updater.check') }}"
        data-install-url="{{ route('desktop.updater.install') }}"
        data-force-install="{{ ! empty($updater['force_install']) ? '1' : '0' }}"
        data-csrf="{{ csrf_token() }}"
    >
        <div class="desktop-updater-card">
            <p class="desktop-updater-brand">{{ config('app.name') }}</p>
            <h2 id="desktop-updater-title">Updating the app</h2>
            <p id="desktop-updater-desc">
                No action needed. The update downloads and installs by itself. The app will reopen when ready.
            </p>

            <p id="desktop-updater-phase" class="desktop-updater-phase">{{ $initialPhase }}</p>
            <p id="desktop-updater-version">
                @if ($initialVersion !== '')
                    Version {{ $initialVersion }}
                @endif
            </p>

            <p id="desktop-updater-percent" class="desktop-updater-percent">{{ $initialPercent }}%</p>

            <div class="desktop-updater-track" aria-hidden="true">
                <div id="desktop-updater-bar" class="desktop-updater-fill" style="width: {{ $initialPercent }}%"></div>
            </div>

            <p class="desktop-updater-note">Do not close or power off this computer. The app will reopen by itself when the update is done.</p>
        </div>
    </div>

    <script>
        (function () {
            const overlay = document.getElementById('desktop-updater-overlay');
            if (!overlay) return;

            const phaseEl = document.getElementById('desktop-updater-phase');
            const versionEl = document.getElementById('desktop-updater-version');
            const percentEl = document.getElementById('desktop-updater-percent');
            const barEl = document.getElementById('desktop-updater-bar');
            const statusUrl = overlay.dataset.statusUrl;
            const checkUrl = overlay.dataset.checkUrl;
            const installUrl = overlay.dataset.installUrl;
            const csrf = overlay.dataset.csrf || '';

            let installTriggered = false;
            let pollTimer = null;
            let active = !overlay.classList.contains('is-hidden');

            function setPercent(pct) {
                const n = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
                percentEl.textContent = n + '%';
                barEl.style.width = n + '%';
                return n;
            }

            function showOverlay(phase, version, percent) {
                active = true;
                overlay.classList.remove('is-hidden');
                document.documentElement.style.overflow = 'hidden';
                phaseEl.textContent = phase;
                versionEl.textContent = version ? ('Version ' + version) : '';
                setPercent(percent);
                schedulePoll(1500);
            }

            function hideOverlay() {
                active = false;
                overlay.classList.add('is-hidden');
                document.documentElement.style.overflow = '';
                schedulePoll(10000);
            }

            function triggerInstall() {
                if (installTriggered || !installUrl) return;
                installTriggered = true;
                showOverlay('Installing update — app will reopen…', versionEl.textContent.replace(/^Version\s*/, '') || '', 100);

                fetch(installUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                }).catch(function () {
                    installTriggered = false;
                });
            }

            function applyStatus(status) {
                if (!status || !status.enabled) {
                    hideOverlay();
                    return;
                }

                const forceInstall = status.force_install !== false;

                if (status.installing && status.installing.version) {
                    showOverlay('Installing update — app will reopen…', status.installing.version, status.installing.percent != null ? status.installing.percent : 100);
                    return;
                }

                if (status.pending && status.pending.version) {
                    const v = status.pending.version;
                    if (forceInstall) {
                        showOverlay('Installing update — app will reopen…', v, 100);
                        triggerInstall();
                    } else {
                        hideOverlay();
                    }
                    return;
                }

                if (status.downloading) {
                    showOverlay('Downloading update', status.downloading.version || '', status.downloading.percent != null ? status.downloading.percent : 0);
                    return;
                }

                hideOverlay();
            }

            function refreshStatus() {
                fetch(statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(applyStatus)
                    .catch(function () {});
            }

            function triggerCheck() {
                if (!checkUrl) return;
                fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                }).catch(function () {});
            }

            function schedulePoll(ms) {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = window.setInterval(refreshStatus, ms);
            }

            window.addEventListener('keydown', function (e) {
                if (!active) return;
                e.preventDefault();
                e.stopPropagation();
            }, true);

            triggerCheck();
            refreshStatus();
            schedulePoll(active ? 1500 : 10000);
            if (active) {
                document.documentElement.style.overflow = 'hidden';
            }
            window.setInterval(triggerCheck, 5 * 60 * 1000);
        })();
    </script>
@endif
