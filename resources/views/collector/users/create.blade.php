@extends('collector.layout')

@section('title', config('app.name').' — Add device user')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('users.index', ['device_id' => $device->id]) }}">← Device users</a>
        · Add user on <strong>{{ $device->name }}</strong>
    </p>

    <div class="card">
        <h2>Enroll user on device</h2>
        <p class="muted">
            Saves the user record to <code>{{ $device->ip_address }}:{{ $device->port }}</code>.
            After saving, you can enroll a fingerprint on the K30 sensor from the edit screen.
        </p>

        <form method="post" action="{{ route('users.store') }}">
            @csrf
            <input type="hidden" name="device_id" value="{{ $device->id }}">

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="user_id">User ID (employee no.)</label>
                    <input type="text" id="user_id" name="user_id" value="{{ old('user_id') }}" required maxlength="64" placeholder="1001">
                    @error('user_id')<p class="errors">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Juan Dela Cruz">
                    @error('name')<p class="errors">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="card_number">Card / RFID (optional)</label>
                    <input type="text" id="card_number" name="card_number" value="{{ old('card_number') }}" maxlength="64" placeholder="Badge or card number">
                    @error('card_number')<p class="errors">{{ $message }}</p>@enderror
                </div>
                @include('collector.users.partials.privilege-field', ['selectedPrivilege' => old('privilege', 'User')])
            </div>

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field field-sm">
                    <label for="device_uid">Device UID slot (optional)</label>
                    <input type="number" id="device_uid" name="device_uid" value="{{ old('device_uid') }}" min="1" placeholder="Auto">
                    <p class="muted" style="margin:0.35rem 0 0; font-size:0.8rem; font-weight:400;">
                        Leave blank to use the next free slot on the device.
                    </p>
                    @error('device_uid')<p class="errors">{{ $message }}</p>@enderror
                </div>
            </div>

            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button type="submit">Save to device</button>
                <a href="{{ route('users.index', ['device_id' => $device->id]) }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
