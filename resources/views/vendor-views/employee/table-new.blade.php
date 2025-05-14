@extends('layouts.vendor.app')
@section('title', 'ملازم شامل کریں')

@section('content')
<div class="container mt-4">
    <!-- فارم برائے نیا ریکارڈ -->
    <div class="card mb-4">
        <div class="card-header">نیا ملازم شامل کریں</div>
        <div class="card-body">
            <form action="{{ route('table_employees.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">نام</label>
                    <input type="text" name="name" class="form-control" placeholder="نام درج کریں" required>
                </div>
                <button type="submit" class="btn btn-primary">محفوظ کریں</button>
            </form>
        </div>
    </div>

    <!-- جدول برائے موجودہ ریکارڈز -->
    <div class="card">
        <div class="card-header">ملازمین کی فہرست</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>نام</th>
                        <th>عمل</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->id }}</td>
                            <td>{{ $employee->name }}</td>
                            <td>
                                <a href="{{ route('table_employees.edit', $employee->id) }}" class="btn btn-sm btn-warning">ترمیم</a>
                                <form action="{{ route('table_employees.destroy', $employee->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">حذف کریں</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">کوئی ریکارڈ موجود نہیں۔</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
