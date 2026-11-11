@extends('collector.layout')

@section('title', config('app.name').' — Edit department')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('departments.index') }}">← Departments</a>
        · <span>Edit department</span>
    </p>

    <div class="card">
        <h2>Edit department</h2>
        <form method="post" action="{{ route('departments.update', $department) }}">
            @csrf
            @method('PUT')
            <div class="field" style="margin-bottom: 1rem; max-width: 28rem;">
                <label for="name">Department name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $department->name) }}" required maxlength="150">
                @error('name')<p class="errors">{{ $message }}</p>@enderror
            </div>
            <button type="submit">Save department</button>
            <a href="{{ route('departments.index') }}" class="btn secondary">Cancel</a>
        </form>
    </div>
@endsection
