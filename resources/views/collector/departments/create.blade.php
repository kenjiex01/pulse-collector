@extends('collector.layout')

@section('title', config('app.name').' — Add department')

@section('content')
    <p class="page-breadcrumb muted">
        <a href="{{ route('departments.index') }}">← Departments</a>
        · <span>Add department</span>
    </p>

    <div class="card">
        <h2>Add department</h2>
        <form method="post" action="{{ route('departments.store') }}">
            @csrf
            <div class="field" style="margin-bottom: 1rem; max-width: 28rem;">
                <label for="name">Department name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="150">
                @error('name')<p class="errors">{{ $message }}</p>@enderror
            </div>
            <button type="submit">Save department</button>
            <a href="{{ route('departments.index') }}" class="btn secondary">Cancel</a>
        </form>
    </div>
@endsection
