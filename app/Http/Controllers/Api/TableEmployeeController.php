<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TableEmployee;
use Illuminate\Http\Request;

class TableEmployeeController extends Controller
{
    // Method to get all employees
    public function index()
    {
        // Retrieve all employees
        $employees = TableEmployee::all();

        // Return the employees as a JSON response
        return response()->json($employees);
    }

    // Other methods (store, show, update, destroy) go here
}
