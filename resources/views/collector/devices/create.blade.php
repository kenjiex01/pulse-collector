@extends('collector.layout')

@section('title', config('app.name').' — Add device')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        · <span>Add device</span>
    </p>

    <div class="card">
        <h2>Add ZkTeco device</h2>
        <p class="muted">Enter the device LAN IP (default port 4370 for K30 and most ZkTeco models).</p>

        <form method="post" action="{{ route('devices.store') }}">
            @csrf

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="name">Device name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Main Gate K30">
                    @error('name')<p class="errors">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="model">Model</label>
                    <input type="text" id="model" name="model" value="{{ old('model', $defaultModel) }}" placeholder="K30">
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 1rem;">
                <div class="field">
                    <label for="ip_address">IP address</label>
                    <input type="text" id="ip_address" name="ip_address" value="{{ old('ip_address') }}" required placeholder="192.168.1.201">
                    @error('ip_address')<p class="errors">{{ $message }}</p>@enderror
                </div>
                <div class="field field-sm">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" value="{{ old('port', $defaultPort) }}" min="1" max="65535">
                </div>
                <div class="field field-sm">
                    <label for="comm_key">Comm key</label>
                    <input type="number" id="comm_key" name="comm_key" value="{{ old('comm_key', 0) }}" min="0" title="Use 0 if the device has no comm password">
                    <p class="muted" style="margin:0.35rem 0 0; font-size:0.8rem; font-weight:400;">
                        Device communication password (set on the ZkTeco menu). Use <strong>0</strong> if none is configured.
                    </p>
                </div>
            </div>

            <button type="submit">Save device</button>
        </form>
    </div>
@endsection
