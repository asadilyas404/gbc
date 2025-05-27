<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Food;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\PosOrderAdditionalDtl;
use App\Models\BusinessSetting;
use App\Mail\PlaceOrder;

class POSController extends Controller
{
    public function place_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paid_amount' => 'required',
            'payment_method' => 'required',
            'restaurant_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $restaurant = $request->vendor->restaurants[0];
        $cart = $request['cart'];

        $total_addon_price = 0;
        $product_price = 0;
        $restaurant_discount_amount = 0;

        $order_details = [];
        $order = new Order();
        $order->id = 100000 + Order::all()->count() + 1;
        if (Order::find($order->id)) {
            $order->id = Order::latest()->first()->id + 1;
        }
        $order->payment_status = 'paid';
        $order->order_status = 'delivered';
        $order->order_type = 'pos';
        $order->payment_method = $request->payment_method;
        $order->transaction_reference = $request->paid_amount;
        $order->restaurant_id = $restaurant->id;
        $order->user_id = $request->user_id;
        $order->delivery_charge = 0;
        $order->original_delivery_charge = 0;
        $order->created_at = now();
        $order->updated_at = now();
        foreach ($cart as $c) {
            if (is_array($c)) {
                $product = Food::find($c['food_id']);
                if ($product) {
                    if (count(json_decode($product['variations'], true)) > 0) {
                        $price = Helpers::variation_price($product, json_encode($c['variation']));
                    } else {
                        $price = $product['price'];
                    }

                    $product->tax = $restaurant->tax;
                    $product = Helpers::product_data_formatting($product);
                    $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withOutGlobalScope(App\Scopes\RestaurantScope::class)->whereIn('id', $c['add_on_ids'])->get(), $c['add_on_qtys']);
                    $notes = $request->notes; // get from form

                    $or_d = [
                        'food_id' => $product->id,
                        'item_campaign_id' => null,
                        'food_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => Helpers::tax_calculate($product, $price),
                        'discount_on_food' => Helpers::product_discount_calculate($product, $price, $restaurant),
                        'discount_type' => 'discount_on_product',
                        'variant' => json_encode($c['variant']),
                        'variation' => json_encode([$c['variation']]),
                        'add_ons' => json_encode($addon_data['addons']),
                        'total_add_on_price' => $addon_data['total_add_on_price'],
                        'details' => $notes, // now storing user's textarea input
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price * $or_d['quantity'];
                    $restaurant_discount_amount += $or_d['discount_on_food'] * $or_d['quantity'];
                    $order_details[] = $or_d;
                } else {
                    return response()->json([
                        'errors' => [
                            ['code' => 'campaign', 'message' => 'not found!']
                        ]
                    ], 404);
                }
            }
        }
        // $restaurant_discount = Helpers::get_restaurant_discount($restaurant);
        // if(isset($restaurant_discount))
        // {
        //     if($product_price + $total_addon_price < $restaurant_discount['min_purchase'])
        //     {
        //         $restaurant_discount_amount = 0;
        //     }

        //     if($restaurant_discount_amount > $restaurant_discount['max_discount'])
        //     {
        //         $restaurant_discount_amount = $restaurant_discount['max_discount'];
        //     }
        // }

        if (isset($request['discount'])) {
            $restaurant_discount_amount += $request['discount_type'] == 'percent' && $request['discount'] > 0 ? ((($product_price + $total_addon_price) * $request['discount']) / 100) : $request['discount'];
        }
        $restaurant_discount_amount = round($restaurant_discount_amount, 2);
        $total_price = $product_price + $total_addon_price - $restaurant_discount_amount;
        $tax = isset($request['tax']) ? $request['tax'] : $restaurant->tax;
        $total_tax_amount = ($tax > 0) ? (($total_price * $tax) / 100) : 0;
        $coupon_discount_amount = 0;
        $total_price = $product_price + $total_addon_price - $restaurant_discount_amount - $coupon_discount_amount;

        $tax = $restaurant->tax;
        $total_tax_amount = round(($tax > 0) ? (($total_price * $tax) / 100) : 0, 2);

        // if($restaurant->minimum_order > $product_price + $total_addon_price )
        // {
        //     Toastr::warning(translate('messages.you_need_to_order_at_least', ['amount'=>$restaurant->minimum_order.' '.Helpers::currency_code()]));
        //     return back();
        // }
        // dd(['pro'=>$product_price, 'add'=>$total_addon_price, 'discount'=>$restaurant_discount_amount, 'tax'=>$total_tax_amount ,'cart'=>$cart]);

        try {
            $order->restaurant_discount_amount = $restaurant_discount_amount;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $total_price + $total_tax_amount + $order->delivery_charge;
            $order->save();
            foreach ($order_details as $key => $item) {
                $order_details[$key]['order_id'] = $order->id;
            }
            OrderDetail::insert($order_details);
            return response()->json([
                'message' => translate('messages.order_placed_successfully'),
                'order_id' => $order->id,
                'total_ammount' => $total_price + $order->delivery_charge + $total_tax_amount
            ], 200);
        } catch (\Exception $e) {
            info($e);
        }
        Toastr::warning(translate('messages.failed_to_place_order'));
        return back();
    }

    public function order_list(Request $request)
    {
        $vendor = $request['vendor'];

        $orders = Order::whereHas('restaurant.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->with('customer')
            ->where('order_type', 'pos')
            ->latest()
            ->get();
        $orders = Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function generate_invoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];

        $order = Order::whereHas('restaurant.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->with('customer')
            ->where('id', $request->order_id)
            ->where('order_type', 'pos')
            ->first();

        if ($order) {
            return response()->json([
                'view' => view('vendor-views.pos.order.invoice', compact('order'))->render(),
            ]);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.not_found')]
            ]
        ], 404);
    }

    public function get_customers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $key = explode(' ', $request['search']);
        $data = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })
            ->limit(8)
            ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')]);

        $data[] = (object)['id' => false, 'text' => translate('messages.walk_in_customer')];

        return response()->json($data);
    }

    // public function order_place(Request $request)
    // {
    //     // Validation rules
    //     $data = $request->validate([
    //         'cart' => 'required|array|min:1',
    //         'cart.*.id' => 'required|integer',
    //         'cart.*.price' => 'required|numeric|min:0',
    //         'cart.*.quantity' => 'required|integer|min:1',
    //         'cart.*.notes' => 'nullable|string',
    //         'cart.*.discount_type' => 'nullable|string',
    //         'cart.*.variation' => 'nullable|string',
    //         'cart.*.add_ons' => 'nullable|array',

    //         'table_id' => 'required|integer|exists:table_employees,id',
    //         'user_id' => 'required|integer|exists:users,id',
    //         'restaurant_id' => 'required|integer|exists:restaurants,id',
    //         'restaurant_discount_amount' => 'nullable|numeric|min:0',
    //         'order_type' => 'required|string',
    //         'address' => 'required|array',
    //         'address.distance' => 'required|numeric|min:0',
    //         'address.free_delivery_by' => 'required|numeric|min:0',
    //         'additional_data' => 'nullable|array',
    //     ]);

    //     \DB::beginTransaction();

    //     try {
    //         // Create Order
    //         $order = new Order();
    //         $order->user_id = $data['user_id'];
    //         $order->table_id = $data['table_id'];
    //         $order->restaurant_id = $data['restaurant_id'];
    //         $order->restaurant_discount_amount = $data['restaurant_discount_amount'] ?? 0;
    //         $order->order_type = $data['order_type'];
    //         $order->distance = $data['address']['distance'];
    //         $order->free_delivery_by = $data['address']['free_delivery_by'];
    //         $order->save();

    //         // Create order details for each cart item
    //         foreach ($data['cart'] as $item) {
    //             $orderDetail = new OrderDetail();
    //             $orderDetail->order_id = $order->id;
    //             $orderDetail->product_id = $item['id']; // Assuming product_id needed
    //             $orderDetail->price = $item['price'];
    //             $orderDetail->quantity = $item['quantity'];
    //             $orderDetail->notes = $item['notes'] ?? null;
    //             $orderDetail->discount_type = !empty($item['discount_type']) ? $item['discount_type'] : 'none';
    //             $orderDetail->variation = $item['variation'] ?? null;
    //             $orderDetail->add_ons = json_encode($item['add_ons'] ?? []);
    //             $orderDetail->save();
    //         }

    //         // Save any additional data if present
    //         if (!empty($data['additional_data'])) {
    //             $posAdditional = new PosOrderAdditionalDtl();
    //             $posAdditional->order_id = $order->id;

    //             // Loop over additional_data and set model attributes dynamically if needed
    //             foreach ($data['additional_data'] as $key => $value) {
    //                 $posAdditional->$key = $value;
    //             }

    //             $posAdditional->save();
    //         }

    //         \DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order placed successfully',
    //             'order_id' => $order->id,
    //         ], 201);

    //     } catch (\Exception $e) {
    //         \DB::rollBack();
    //         \Log::error('Order placement failed: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to place order',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // public function order_place(Request $request)
    // {
    //     $data = $request->validate([
    //         'cart' => 'required|array',
    //         'cart.*.id' => 'required|integer',
    //         'cart.*.price' => 'required|numeric',
    //         'cart.*.quantity' => 'required|integer',
    //         'cart.*.notes' => 'nullable|string',
    //         'cart.*.discount_type' => 'nullable|string',
    //         'cart.*.variation' => 'nullable|string',
    //         'cart.*.add_ons' => 'nullable|array',

    //         'user_id' => 'nullable|integer',
    //         'order_id' => 'nullable|integer',
    //         'table_id' => 'nullable|integer|exists:table_employees,id',
    //         'restaurant_id' => 'required|integer',
    //         'order_type' => 'required|string',
    //         'restaurant_discount_amount' => 'nullable|numeric',

    //         'address' => 'nullable|array',
    //         'address.distance' => 'nullable|numeric',
    //         'address.free_delivery_by' => 'nullable|numeric',

    //         'additional_data' => 'nullable|array',
    //     ]);

    //     \DB::beginTransaction();

    //     try {
    //         // ✅ Create and save order
    //         $order = new Order();
    //         $order->user_id = $data['user_id'] ?? auth()->id(); // fallback to logged-in user
    //         $order->table_id = $data['table_id'] ?? null;
    //         $order->restaurant_id = $data['restaurant_id']; // Required field
    //         $order->restaurant_discount_amount = $data['restaurant_discount_amount'] ?? 0;
    //         $order->order_type = $data['order_type']; // Required field
    //         $order->distance = $data['address']['distance'] ?? 0;
    //         $order->free_delivery_by = $data['address']['free_delivery_by'] ?? null;
    //         $order->save();

    //         // ✅ Create order details
    //         foreach ($data['cart'] as $item) {
    //             $orderDetail = new OrderDetail();
    //             $orderDetail->food_id = $item['id'];
    //             $orderDetail->order_id = $order->id;
    //             $orderDetail->price = $item['price'];
    //             $orderDetail->quantity = $item['quantity'];
    //             $orderDetail->notes = $item['notes'] ?? null;
    //             $orderDetail->discount_type = $item['discount_type'] ?? 'none';
    //             $orderDetail->variation = $item['variation'] ?? null;
    //             $orderDetail->add_ons = json_encode($item['add_ons'] ?? []);
    //             $orderDetail->save();

    //             // ✅ Save additional data (if exists)
    //             if (!empty($data['additional_data'])) {
    //                 $posAdditional = new PosOrderAdditionalDtl();
    //                 $posAdditional->order_id = $data['order_id']; // Required field
    //                 $posAdditional->restaurant_id = $data['restaurant_id'];
    //                 $posAdditional->customer_name = $data['additional_data']['customer_name'] ?? null;
    //                 $posAdditional->car_number = preg_replace('/\D/', '', $data['additional_data']['car_number'] ?? '0');
    //                 $posAdditional->phone = preg_replace('/\D/', '', $data['additional_data']['phone'] ?? '0');
    //                 $posAdditional->bank_account = preg_replace('/[^0-9]/', '', $data['additional_data']['bank_account'] ?? '0');
    //                 $posAdditional->invoice_amount = $data['additional_data']['invoice_amount'] ?? 0;
    //                 $posAdditional->cash_paid = $data['additional_data']['cash_paid'] ?? 0;
    //                 $posAdditional->card_paid = $data['additional_data']['card_paid'] ?? 0;
    //                 $posAdditional->save();
    //             }
    //         }

    //         \DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order placed successfully',
    //             'order_id' => $order->id
    //         ], 201);
    //     } catch (\Exception $e) {
    //         \DB::rollBack();
    //         \Log::error('Order placement failed: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to place order',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    //     public function order_place(Request $request)
    // {
    //     // Step 1: Extract cart items from flat input keys like id_0, price_0, quantity_0, etc.
    //     $cart = [];

    //     // Detect max index dynamically from keys starting with 'id_'
    //     $indices = [];
    //     foreach ($request->all() as $key => $value) {
    //         if (preg_match('/^id_(\d+)$/', $key, $matches)) {
    //             $indices[] = intval($matches[1]);
    //         }
    //     }
    //     $indices = array_unique($indices);
    //     sort($indices);

    //     foreach ($indices as $i) {
    //         if ($request->has("id_$i")) {
    //             $cart[] = [
    //                 'id' => $request->input("id_$i"),
    //                 'price' => $request->input("price_$i"),
    //                 'quantity' => $request->input("quantity_$i"),
    //                 'notes' => $request->input("notes_$i", null),
    //                 'discount_type' => $request->input("discount_type_$i", null),
    //                 'variation' => $request->input("variation_$i", null),
    //                 'add_ons' => $request->input("add_ons_$i") 
    //                     ? json_decode($request->input("add_ons_$i"), true) 
    //                     : [],  // expecting add_ons as JSON string, decode it
    //             ];
    //         }
    //     }

    //     // Step 2: Merge cart array into request for validation
    //     $request->merge(['cart' => $cart]);

    //     // Step 3: Validate input
    //     $data = $request->validate([
    //         'cart' => 'required|array|min:1',
    //         'cart.*.id' => 'required|integer',
    //         'cart.*.price' => 'required|numeric',
    //         'cart.*.quantity' => 'required|integer',
    //         'cart.*.notes' => 'nullable|string',
    //         'cart.*.discount_type' => 'nullable|string',
    //         'cart.*.variation' => 'nullable|string',
    //         'cart.*.add_ons' => 'nullable|array',

    //         'user_id' => 'nullable|integer',
    //         'order_id' => 'nullable|integer',
    //         'table_id' => 'nullable|integer|exists:table_employees,id',
    //         'restaurant_id' => 'required|integer',
    //         'order_type' => 'required|string',
    //         'restaurant_discount_amount' => 'nullable|numeric',

    //         'address' => 'nullable|array',
    //         'address.distance' => 'nullable|numeric',
    //         'address.free_delivery_by' => 'nullable|numeric',

    //         'additional_data' => 'nullable|array',
    //     ]);

    //     \DB::beginTransaction();

    //     try {
    //         // Create and save order
    //         $order = new Order();
    //         $order->user_id = $data['user_id'] ?? auth()->id();
    //         $order->table_id = $data['table_id'] ?? null;
    //         $order->restaurant_id = $data['restaurant_id'];
    //         $order->restaurant_discount_amount = $data['restaurant_discount_amount'] ?? 0;
    //         $order->order_type = $data['order_type'];
    //         $order->distance = $data['address']['distance'] ?? 0;
    //         $order->free_delivery_by = $data['address']['free_delivery_by'] ?? null;
    //         $order->save();

    //         // Create order details
    //         foreach ($data['cart'] as $item) {
    //             $orderDetail = new OrderDetail();
    //             $orderDetail->food_id = $item['id'];
    //             $orderDetail->order_id = $order->id;
    //             $orderDetail->price = $item['price'];
    //             $orderDetail->quantity = $item['quantity'];
    //             $orderDetail->notes = $item['notes'] ?? null;
    //             $orderDetail->discount_type = $item['discount_type'] ?? 'none';
    //             $orderDetail->variation = $item['variation'] ?? null;
    //             $orderDetail->add_ons = json_encode($item['add_ons'] ?? []);
    //             $orderDetail->save();
    //         }

    //         // Save additional data (if exists)
    //         if (!empty($data['additional_data'])) {
    //             $posAdditional = new PosOrderAdditionalDtl();
    //             $posAdditional->order_id = $data['order_id'] ?? $order->id;
    //             $posAdditional->restaurant_id = $data['restaurant_id'];
    //             $posAdditional->customer_name = $data['additional_data']['customer_name'] ?? null;
    //             $posAdditional->car_number = preg_replace('/\D/', '', $data['additional_data']['car_number'] ?? '0');
    //             $posAdditional->phone = preg_replace('/\D/', '', $data['additional_data']['phone'] ?? '0');
    //             $posAdditional->bank_account = preg_replace('/[^0-9]/', '', $data['additional_data']['bank_account'] ?? '0');
    //             $posAdditional->invoice_amount = $data['additional_data']['invoice_amount'] ?? 0;
    //             $posAdditional->cash_paid = $data['additional_data']['cash_paid'] ?? 0;
    //             $posAdditional->card_paid = $data['additional_data']['card_paid'] ?? 0;
    //             $posAdditional->save();
    //         }

    //         \DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order placed successfully',
    //             'order_id' => $order->id
    //         ], 201);
    //     } catch (\Exception $e) {
    //         \DB::rollBack();
    //         \Log::error('Order placement failed: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to place order',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


public function order_place(Request $request)
{
    // ✅ Step 1: Validate required fields except variations.* because we are sending JSON string
    $validated = $request->validate([
        'variations' => 'required|string', // JSON string
        'restaurant_id' => 'required|integer',
        'user_id' => 'nullable|integer',
        'order_type' => 'required|string',
        'restaurant_discount_amount' => 'nullable|numeric',
        'distance' => 'nullable|numeric',
        'free_delivery_by' => 'nullable|numeric',
        'table_id' => 'required|integer',
        'customer_name' => 'nullable|string',
        'car_number' => 'nullable|string',
        'phone' => 'nullable|string',
        'invoice_amount' => 'nullable|numeric',
        'cash_paid' => 'nullable|numeric',
        'card_paid' => 'nullable|numeric',
    ]);

    \DB::beginTransaction();

    try {
        // ✅ Step 2: Create the order
        $order = new Order();
        $order->user_id = $request->input('user_id') ?? auth()->id();
        $order->restaurant_id = $request->input('restaurant_id');
        $order->table_id = $request->input('table_id');
        $order->restaurant_discount_amount = $request->input('restaurant_discount_amount') ?? 0;
        $order->order_type = $request->input('order_type');
        $order->distance = $request->input('distance') ?? 0;
        $order->free_delivery_by = $request->input('free_delivery_by') ?? null;
        $order->save();

        // ✅ Step 3: Decode the JSON string of variations
        $variationsJson = $request->input('variations');
        $variations = json_decode($variationsJson, true);

        if (!is_array($variations)) {
            return response()->json(['error' => 'Invalid variations format'], 422);
        }

        // ✅ Step 4: Save each variation
        foreach ($variations as $item) {
            $orderDetail = new OrderDetail();
            $orderDetail->food_id = $item['food_id'];
            $orderDetail->order_id = $order->id;
            $orderDetail->price = $item['price'];
            $orderDetail->quantity = $item['quantity'];
            $orderDetail->notes = $item['notes'] ?? null;
            $orderDetail->discount_type = $item['discount_type'] ?? 'none';
            $orderDetail->variation = $item['variation'] ?? null;
            $orderDetail->add_ons = json_encode($item['add_ons'] ?? []);
            $orderDetail->save();
        }

        // ✅ Step 5: Save additional fields manually
        $posAdditional = new PosOrderAdditionalDtl();
        $posAdditional->order_id = $order->id;
        $posAdditional->restaurant_id = $request->input('restaurant_id');
        $posAdditional->customer_name = $request->input('customer_name');
        $posAdditional->car_number = $request->input('car_number') ?? '0';
        $posAdditional->phone = $request->input('phone') ?? '0';
        $posAdditional->invoice_amount = $request->input('invoice_amount') ?? 0;
        $posAdditional->cash_paid = $request->input('cash_paid') ?? 0;
        $posAdditional->card_paid = $request->input('card_paid') ?? 0;
        $posAdditional->save();

        \DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $order->id
        ], 201);

    } catch (\Exception $e) {
        \DB::rollBack();
        \Log::error('Order placement failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to place order',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
