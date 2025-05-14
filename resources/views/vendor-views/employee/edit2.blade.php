@extends('layouts.vendor.app')
@section('title', 'Edit Employee')

@section('content')
<div class="container mt-4">
    <!-- Form for editing an existing record -->
    <div class="card mb-4">
        <div class="card-header">Edit Employee</div>
        <div class="card-body">
            <form action="{{ route('table_employees.update', $employee->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $employee->name }}" required>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
</div>
@endsection
