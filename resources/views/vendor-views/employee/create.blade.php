@extends('layouts.vendor.app')
@section('title', 'Add Employee')

@section('content')
<div class="container mt-4">
    <!-- Form for adding new record -->
    <div class="card mb-4">
        <div class="card-header">Add New Employee</div>
        <div class="card-body">
            <form action="{{ route('table_employees.store')}}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter Name" required>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
</div>
@endsection
