@extends('collector.layout')

@section('title', config('app.name').' — Upload backup JSON')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        · <a href="{{ route('archives.index') }}">Deleted log backups</a>
        · <span>Upload backup JSON</span>
    </p>

    <div class="card">
        <h2 style="margin-top:0;">Upload backup JSON</h2>
        <p class="muted">
            Import a retention backup JSON file (<code>kind: deleted_attendance</code>) to restore deleted punches into this collector.
            Duplicate punches are skipped automatically. Unmatched rows are ignored when the campus/device is not configured locally.
        </p>

        <form method="post" action="{{ route('archives.upload.store') }}" enctype="multipart/form-data" style="margin-top:1rem;">
            @csrf
            <div class="field">
                <label for="backup_json">Backup JSON file</label>
                <input type="file" id="backup_json" name="backup_json" accept=".json,application/json" required>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.75rem;">
                <button type="submit" class="btn">Upload and restore</button>
                <a href="{{ route('archives.index') }}" class="btn secondary">View backup files</a>
            </div>
        </form>
    </div>
@endsection
