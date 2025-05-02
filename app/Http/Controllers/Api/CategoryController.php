<?php

// app/Http/Controllers/Api/CategoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function apiIndex()
    {
        $categories = Category::all(); // Agar aap specific columns chahte hain to: ->select('id', 'name')->get()
    
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
    
}

