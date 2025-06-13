<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class DataReceiveController extends Controller
{
     public function receive(Request $request)
    {
        $data = $request->all();

        // Check if order already exists
        $order = Order::updateOrCreate(
            ['id' => $data['id']], // Match by ID
            $data // Update with full data
        );

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }
}
