<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PosOrderAdditionalDtl;
use App\Models\KitchenOrderStatusLog;
use App\Models\BusinessSetting;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class WaiterController extends Controller
{
    public function waiter_place_order(Request $request)
    {
        dd($request->all());
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'restaurant_id' => 'required|integer',
                'table_id' => 'required|integer',
                'order_type' => 'required|in:indoor,outdoor,take_away,delivery',
                'waiter_id' => 'required|string', // This will be stored in order_taken_by
                'cart' => 'required|array|min:1',
                'cart.*.id' => 'required|integer',
                'cart.*.quantity' => 'required|integer|min:1',
                'cart.*.price' => 'required|numeric|min:0',
                'cart.*.variations' => 'nullable|array',
                'cart.*.variations.*.heading' => 'required_with:cart.*.variations|string',
                'cart.*.variations.*.selected_option' => 'required_with:cart.*.variations|array',
                'cart.*.variations.*.selected_option.id' => 'required_with:cart.*.variations.*.selected_option|integer',
                'cart.*.variations.*.selected_option.name' => 'required_with:cart.*.variations.*.selected_option|string',
                'cart.*.variations.*.addons' => 'nullable|array',
                'cart.*.variations.*.addons.*.id' => 'required_with:cart.*.variations.*.addons|integer',
                'cart.*.variations.*.addons.*.quantity' => 'required_with:cart.*.variations.*.addons|integer|min:1',
                'cart.*.add_ons' => 'nullable|array',
                'cart.*.add_on_qtys' => 'nullable|array',
                'cart.*.notes' => 'nullable|string',
                'cart.*.discount' => 'nullable|numeric|min:0',
                // 'payment_method' => 'required|in:cash,card,cash_card',
                // 'cash_paid' => 'nullable|numeric|min:0',
                // 'card_paid' => 'nullable|numeric|min:0',
                'customer_name' => 'nullable|string',
                'phone' => 'nullable|string',
                'car_number' => 'nullable|string',
                // 'bank_account' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cart = $request->cart;
            $total_addon_price = 0;
            $product_price = 0;
            $restaurant_discount_amount = 0;
            $order_details = [];

            // Process cart items
            foreach ($cart as $c) {
                $product = Food::find($c['id']);
                if (!$product) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Product not found: ' . $c['id']
                    ], 400);
                }

                $price = $c['price'];
                $product->tax = 0;
                $product = Helpers::product_data_formatting($product);

                // Calculate addon prices for general addons
                $addon_data = ['total_add_on_price' => 0, 'addons' => []];
                if (isset($c['add_ons']) && is_array($c['add_ons'])) {
                    $addon_data = Helpers::calculate_addon_price(
                        \App\Models\AddOn::whereIn('id', $c['add_ons'])->get(),
                        $c['add_on_qtys'] ?? []
                    );
                }

                // Prepare addon data for stock checking (including variation-specific addons)
                $all_addon_ids = $c['add_ons'] ?? [];
                $all_addon_qtys = $c['add_on_qtys'] ?? [];

                // Add variation-specific addons to stock checking
                if (isset($c['variations']) && is_array($c['variations'])) {
                    foreach ($c['variations'] as $variation) {
                        if (isset($variation['addons']) && is_array($variation['addons'])) {
                            foreach ($variation['addons'] as $addon) {
                                $all_addon_ids[] = $addon['id'];
                                $all_addon_qtys[] = $addon['quantity'];
                            }
                        }
                    }
                }

                // Check stock
                if (count($all_addon_ids) > 0) {
                    $addonAndVariationStock = Helpers::addonAndVariationStockCheck(
                        $product,
                        $c['quantity'],
                        $all_addon_qtys,
                        [], // No variation option IDs in this format
                        $all_addon_ids,
                        true
                    );

                    if (data_get($addonAndVariationStock, 'out_of_stock') != null) {
                        return response()->json([
                            'status' => false,
                            'message' => data_get($addonAndVariationStock, 'out_of_stock')
                        ], 400);
                    }
                }

                // Increment sell count
                $product->increment('sell_count', $c['quantity']);

                // Process variations with selected options
                $complete_variations = [];
                $total_variation_addon_price = 0;

                if (isset($c['variations']) && is_array($c['variations'])) {
                    foreach ($c['variations'] as $variation) {
                        $variationData = [
                            'heading' => $variation['heading'],
                            'selected_option' => [
                                'id' => $variation['selected_option']['id'],
                                'name' => $variation['selected_option']['name']
                            ],
                            'addons' => []
                        ];

                        // Process variation-specific addons
                        if (isset($variation['addons']) && is_array($variation['addons'])) {
                            foreach ($variation['addons'] as $addon) {
                                $addonModel = \App\Models\AddOn::find($addon['id']);
                                if ($addonModel) {
                                    $variationData['addons'][] = [
                                        'id' => $addon['id'],
                                        'name' => $addonModel->name,
                                        'price' => $addonModel->price,
                                        'quantity' => $addon['quantity']
                                    ];
                                    $total_variation_addon_price += ($addonModel->price * $addon['quantity']);
                                }
                            }
                        }

                        $complete_variations[] = $variationData;
                    }
                }

                // Calculate total addon price including variation-specific addons
                $total_addon_price_for_item = $addon_data['total_add_on_price'] + $total_variation_addon_price;

                $order_details[] = [
                    'food_id' => $c['id'],
                    'food_details' => json_encode($product),
                    'quantity' => $c['quantity'],
                    'price' => $price,
                    'tax_amount' => Helpers::tax_calculate($product, $price),
                    'discount_on_food' => $c['discount'] ?? 0,
                    'discount_type' => 'discount_on_product',
                    'variation' => json_encode($complete_variations),
                    'add_ons' => json_encode($addon_data['addons']),
                    'total_add_on_price' => $total_addon_price_for_item,
                    'notes' => $c['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $total_addon_price += $total_addon_price_for_item;
                $product_price += $price * $c['quantity'];
                $restaurant_discount_amount += ($c['discount'] ?? 0) * $c['quantity'];
            }

            // Create order
            $order = new Order();
            $order->id = Helpers::generateGlobalId($request->restaurant_id);

            // Generate order serial
            $today = Carbon::today();
            $todayOrderCount = Order::whereDate('created_at', $today)->count();
            $dayPart = $today->format('d');
            $sequencePart = str_pad($todayOrderCount + 1, 3, '0', STR_PAD_LEFT);
            $order->order_serial = "{$dayPart}-{$sequencePart}";

            $order->created_at = now();
            $order->schedule_at = now();
            $order->payment_status = 'draft';
            $order->kitchen_status = 'pending';
            $order->order_status = 'pending';
            $order->order_type = $request->order_type;
            $order->table_id = $request->table_id;
            $order->order_taken_by = $request->waiter_id;
            $order->restaurant_id = $request->restaurant_id;
            $order->zone_id = 1;
            $order->delivery_charge = 0; // No delivery charge for indoor orders
            $order->original_delivery_charge = 0;
            $order->delivery_address = null;
            $order->checked = 1;
            $order->updated_at = now();
            $order->otp = rand(1000, 9999);

            // Calculate totals
            $total_price = $product_price + $total_addon_price - $restaurant_discount_amount;
            $tax = 0;
            $order->tax_status = 'excluded';

            $tax_included = BusinessSetting::where(['key' => 'tax_included'])->first()->value ?? 0;
            if ($tax_included == 1) {
                $order->tax_status = 'included';
            }

            $total_tax_amount = Helpers::product_tax($total_price, $tax, $order->tax_status == 'included');
            $tax_a = $order->tax_status == 'included' ? 0 : $total_tax_amount;

            $order->restaurant_discount_amount = $restaurant_discount_amount;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $total_price + $tax_a;
            $order->adjusment = $order->order_amount;
            // $order->payment_method = $request->payment_method;

            DB::beginTransaction();

            try {
                $order->save();

                // Create kitchen status log
                KitchenOrderStatusLog::create([
                    "status" => 'pending',
                    "order_id" => $order->id
                ]);

                // Create order details
                foreach ($order_details as $key => $item) {
                    $order_details[$key]['order_id'] = $order->id;
                }
                OrderDetail::insert($order_details);

                // Create POS order additional details
                $posOrderDtl = new PosOrderAdditionalDtl();
                $posOrderDtl->order_id = $order->id;
                $posOrderDtl->restaurant_id = $request->restaurant_id;
                $posOrderDtl->customer_name = $request->customer_name;
                $posOrderDtl->car_number = $request->car_number;
                $posOrderDtl->phone = $request->phone;
                $posOrderDtl->invoice_amount = $order->order_amount;
                // $posOrderDtl->cash_paid = $request->cash_paid ?? 0;
                // $posOrderDtl->card_paid = $request->card_paid ?? 0;
                // $posOrderDtl->bank_account = $request->bank_account;
                $posOrderDtl->save();

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Order placed successfully',
                    'data' => [
                        'order_id' => $order->id,
                        'order_serial' => $order->order_serial,
                        'order_amount' => $order->order_amount,
                        'payment_status' => $order->payment_status,
                        'order_status' => $order->order_status,
                        'table_id' => $order->table_id,
                        'order_type' => $order->order_type,
                        'created_at' => $order->created_at->format('Y-m-d H:i:s')
                    ]
                ], 200);

            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }

        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to place order: ' . $exception->getMessage()
            ], 500);
        }
    }
}
