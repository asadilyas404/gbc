<?php

namespace App\Http\Controllers;

use App\Models\TableEmployee;
use Illuminate\Http\Request;

class TableEmployeeController extends Controller
{
public function index()
{
    $employees = TableEmployee::all();
    return view('vendor-views.employee.table-new', compact('employees'));
}

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
    
        TableEmployee::create([
            'name' => $request->name,
        ]);
    
        return redirect()->back()->with('success', 'ملازم کامیابی سے شامل ہو گیا۔');
    }
    
    public function edit($id)
    {
        $employee = TableEmployee::findOrFail($id);
        return view('table_employees.edit', compact('employee'));
    }
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
    
        $employee = TableEmployee::findOrFail($id);
        $employee->update([
            'name' => $request->name,
        ]);
    
        return redirect()->route('table_employees.index')->with('success', 'ملازم کی معلومات کامیابی سے اپ ڈیٹ ہو گئیں۔');
    }
    
    public function destroy($id)
    {
        $employee = TableEmployee::findOrFail($id);
        $employee->delete();
    
        return redirect()->back()->with('success', 'ملازم کامیابی سے حذف ہو گیا۔');
    }
}
