@extends('collector.layout')

@section('title', config('app.name').' — Departments')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('dashboard') }}">← Dashboard</a>
        · <span>Departments</span>
    </p>

    <div class="card">
        <div class="data-table-header">
            <h2 style="margin:0;">Departments</h2>
            <a href="{{ route('departments.create') }}" class="btn">Add department</a>
        </div>
        <p class="muted" style="margin: 0.75rem 0 0;">
            Add a department here before assigning it on Device users.
        </p>
    </div>

    <div class="card data-table-card">
        @if ($departments->isEmpty())
            <p class="muted" style="padding: 1rem 1.25rem; margin: 0;">No departments yet.</p>
        @else
            <div class="data-table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Device users</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $department)
                            <tr>
                                <td>{{ $department->name }}</td>
                                <td class="num">{{ number_format($department->device_users_count) }}</td>
                                <td>
                                    <a href="{{ route('departments.edit', $department) }}" class="view-logs-link">Edit</a>
                                    <form method="post" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Remove this department?');" style="display:inline;">
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
@endsection
