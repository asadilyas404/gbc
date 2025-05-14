@extends('layouts.vendor.app')
@section('title', 'Employee List')

@section('content')
<div class="container mt-4">
    <!-- Table for displaying current records -->
    <div class="card">
        <div class="card-header">Employee List</div>
        <div class="card-body">
            <a href="{{ route('table_employees.create') }}" class="btn btn-primary mb-3">Add New Employee</a>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->id }}</td>
                            <td>{{ $employee->name }}</td>
                            <td>
                                <a href="{{ route('table_employees.edit', $employee->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                <form action="{{ route('table_employees.destroy', $employee->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
