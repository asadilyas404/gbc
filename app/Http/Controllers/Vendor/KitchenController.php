<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Models\Food;
use App\Models\KitchenOrderStatusLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class KitchenController extends Controller
{

    public function index(Request $request)
    {
        $data = $this->getOrderList();

        $data['order_type'] = [
            'take_away' => 'Take Away',
            'delivery' => 'Delivery',
            'dine_in' => 'Dining',
        ];

        return view('vendor-views.kitchen.index', compact('data'));
    }

    public function getOrderList()
    {
        $orders = Order::with('customer', 'kitchen_log')
            ->whereIn('kitchen_status', [
                Helpers::kitchenStatus('pending')['key'],
                Helpers::kitchenStatus('cooking')['key'],
                Helpers::kitchenStatus('ready')['key']
            ])
            ->select('id', 'order_amount', 'order_type', 'kitchen_status', 'order_serial', 'order_date')
            ->orderBy('created_at', 'desc')->get();

        $pending = [];
        $cooking = [];
        $ready = [];

        foreach ($orders as $order) {
            $log = $order->kitchen_log->where('status', $order->kitchen_status)->first();
            if ($log) {
                $timer = "";
                if (isset($log->created_at) && !empty($log->created_at)) {
                    $timer = date('H:i:s', strtotime($log->created_at));
                }
                $order->setAttribute('kitchen_time', $timer);
            }
            if ($order->kitchen_status == 'pending') {
                $pending[] = $order;
            }
            if ($order->kitchen_status == 'cooking') {

                $cooking[] = $order;
            }
            if ($order->kitchen_status == 'ready') {
                $ready[] = $order;
            }
        }
        return [
            'pending' => $pending,
            'cooking' => $cooking,
            'ready' => $ready,
        ];
    }

    public function getAllOrders(Request $request)
    {


        if ($request->type && $request->id) {
            if (!isset(Helpers::kitchenStatus()[$request->type])) {
                return response()->json([
                    'success' => false,
                    'data'    => [],
                    'message' => "Kitchen Status is not available"
                ]);
            }

            $order = Order::where('id', $request->id)->first();
            if ($order) {

                $idSuffix = '1'; // default
                if ($request->type === 'cooking') {
                    $idSuffix = '2';
                } elseif ($request->type === 'ready') {
                    $idSuffix = '3';
                }

                KitchenOrderStatusLog::create([
                    "status"   => $request->type,
                    "order_id" => $order->id,
                    "id"       => $order->id . $idSuffix,
                ]);

                $order->kitchen_status = $request->type;
                $order->order_status   = $request->type;
                $order->is_pushed      = 'N';
                $order->save();
            }
        }



        $data = $this->getOrderList();

        return response()->json(['success' => true, 'data' => $data, 'message' => "list of all orders"]);
    }
}
