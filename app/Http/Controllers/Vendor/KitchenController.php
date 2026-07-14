<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Jobs\POSOrderReady;
use App\Models\Food;
use App\Models\KitchenOrderStatusLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $branchId = Helpers::get_restaurant_id();
        $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
        $orderDate = $branch ? $branch->orders_date : null;
        $orders = Order::with('customer', 'kitchen_log','details', 'partner', 'details.food', 'details.food.latestKitchenLog')
            ->whereIn('kitchen_status', [
                Helpers::kitchenStatus('pending')['key'],
                Helpers::kitchenStatus('cooking')['key'],
                // Helpers::kitchenStatus('ready')['key']
            ])
            // ->select('id', 'order_amount', 'order_type', 'kitchen_status', 'order_serial', 'order_date')
            ->where('order_date', $orderDate)
            ->orderBy('created_at', 'desc')->get();

        $pending = [];
        $cooking = [];
        $ready = [];

        foreach ($orders as $order) {
            
            $timer = "";
            if (isset($order->created_at) && !empty($order->created_at)) {
                $timer = date('H:i:s', strtotime($order->created_at));
            }
            $order->setAttribute('kitchen_time', $timer);
            
            if ($order->kitchen_status == 'pending') {
                $pending[] = $order;
            }
            if ($order->kitchen_status == 'cooking') {
                $cooking[] = $order;
            }
            // if ($order->kitchen_status == 'ready') {
            //     $ready[] = $order;
            // }
        }
        return [
            'orders'  => $orders,
            'pending' => $pending,
            'cooking' => $cooking,
            // 'ready' => $ready,
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

            $order = Order::where('id', $request->id)->with('customer')->first();
            if ($order) {

                $idSuffix = '1'; // default
                if ($request->type === 'cooking') {
                    $idSuffix = '2';
                } elseif ($request->type === 'ready') {
                    $idSuffix = '3';
                } elseif ($request->type === 'completed') {
                    $idSuffix = '4';
                }

                // KitchenOrderStatusLog::create([
                //     "status"   => $request->type,
                //     "order_id" => $order->id,
                //     "id"       => $order->id . $idSuffix,
                // ]);

                $order->kitchen_status = $request->type;
                $order->order_status   = $request->type;
                $order->is_pushed      = 'N';
                $order->save();

                if($request->type == 'ready'){
                    // Send WhatsApp message when order is ready
                    $phone = $order->customer ? $order->customer->customer_mobile_no : null;
                    if ($phone) {
                        // POSOrderReady::dispatch($phone, $order->id, 'ready')->onConnection('database')->onQueue('whatsapp');
                    }
                }

                // Updating the food items' kitchen status
                // foreach ($order->details as $detail) {
                //     // Update detail cooking_status
                //     $detail->kitchen_status = $request->type;
                //     $detail->preparing_by = Auth::guard('vendor')->id() ?? Auth::guard('vendor_employee')->id();
                //     $detail->save();
                // }
            }
        }

        $data = $this->getOrderList();

        return response()->json(['success' => true, 'data' => $data, 'message' => "list of all orders"]);
    }
}
