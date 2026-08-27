@extends('collector.layout')

@section('title', config('app.name').' — Edit device user')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('users.index', ['device_id' => $deviceUser->biometric_device_id]) }}">← Device users</a>
        · Edit <strong>{{ $deviceUser->user_id }}</strong> on {{ $device->name }}
    </p>

    <div class="card">
        <h2>Update user on device</h2>
        <p class="muted">
            Changes are pushed to <code>{{ $device->ip_address }}:{{ $device->port }}</code>.
            User ID is the employee number on the device (max 24 characters on most K30 firmware).
            Device UID is the internal slot and stays the same.
        </p>

        <form method="post" action="{{ route('users.update', $deviceUser) }}">
            @csrf

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="user_id">User ID (employee no.)</label>
                    <input type="text" id="user_id" name="user_id" value="{{ old('user_id', $deviceUser->user_id) }}" required maxlength="64">
                    @error('user_id')<p class="errors">{{ $message }}</p>@enderror
                </div>
                <div class="field field-sm">
                    <label for="device_uid">Device UID (slot)</label>
                    <input type="text" id="device_uid" value="{{ $deviceUser->device_uid ?? '—' }}" disabled>
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $deviceUser->name) }}" required maxlength="255">
                    @error('name')<p class="errors">{{ $message }}</p>@enderror
                </div>
                @include('collector.users.partials.privilege-field', ['selectedPrivilege' => old('privilege', $deviceUser->privilege ?? 'User')])
            </div>

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="card_number">Card / RFID (optional)</label>
                    <input type="text" id="card_number" name="card_number" value="{{ old('card_number', $deviceUser->card_number) }}" maxlength="64">
                    @error('card_number')<p class="errors">{{ $message }}</p>@enderror
                </div>
            </div>

            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button type="submit">Save and push to device</button>
                <a href="{{ route('users.index', ['device_id' => $deviceUser->biometric_device_id]) }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card" id="enroll-fingerprint">
        <h2>Enroll fingerprint (K30 sensor)</h2>

        @include('collector.users.partials.enroll-result')

        <p class="muted">
            The user must already exist on <code>{{ $device->ip_address }}:{{ $device->port }}</code>.
            Click a finger below, then press the <strong>same finger</strong> on the device scanner when it beeps (often three times).
            This page may wait up to {{ (int) config('biometric.device.enroll_timeout_seconds', 90) }} seconds.
        </p>

        <div class="enroll-progress" id="enroll-progress" hidden role="status" aria-live="polite">
            Enrolling… place the selected finger on the K30 sensor when prompted. Do not close the app.
        </div>

        <form method="post" action="{{ route('users.enroll-fingerprint', $deviceUser) }}" id="enroll-fingerprint-form">
            @csrf
            @include('collector.users.partials.finger-hand-picker', [
                'selectedFinger' => (int) old('finger_index', 6),
                'fingerOptions' => $fingerOptions,
            ])
            <button type="submit" id="enroll-fingerprint-submit">Start fingerprint enrollment</button>
        </form>
    </div>

    @if (session('enroll_result'))
        <script>
            (function () {
                var section = document.getElementById('enroll-fingerprint');
                var banner = document.getElementById('enroll-result-banner');
                if (section) {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                if (banner) {
                    banner.focus({ preventScroll: true });
                }
            })();
        </script>
    @else
        <script>
            (function () {
                var form = document.getElementById('enroll-fingerprint-form');
                if (!form) return;
                form.addEventListener('submit', function () {
                    var btn = document.getElementById('enroll-fingerprint-submit');
                    var progress = document.getElementById('enroll-progress');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Waiting for K30 scans…';
                    }
                    if (progress) {
                        progress.hidden = false;
                    }
                });
            })();
        </script>
    @endif
@endsection
