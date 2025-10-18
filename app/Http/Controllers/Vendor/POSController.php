<?php

namespace App\Http\Controllers\Vendor;

use Carbon\Carbon;
use App\Models\Food;
use App\Models\User;
use App\Models\Order;
use App\Mail\PlaceOrder;
use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\PosOrderAdditionalDtl;
use App\Models\SaleCustomer;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\KitchenOrderStatusLog;
use Illuminate\Support\Facades\Auth;

class POSController extends Controller
{
    public function index(Request $request)
    {
        $time = Carbon::now()->toTimeString();
        $category = $request->query('category_id', 0);
        $categories = Category::active()->get();
        $keyword = $request->query('keyword', false);
        $key = explode(' ', $keyword);
        $products = Food::active()->when($category, function ($query) use ($category) {
            $query->whereHas('category', function ($q) use ($category) {
                return $q->whereId($category)->orWhere('parent_id', $category);
            });
        })
            ->when($keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })->available($time)
            ->latest()->paginate(10);
        return view('vendor-views.pos.index', compact('categories', 'products', 'category', 'keyword'));
    }

    // public function indexNew(Request $request)
    // {
    //     $time = Carbon::now()->toTimeString();
    //     $category = $request->query('category_id', 0);
    //     $categories = Category::active()->where('parent_id',0)->get();
    //     $subcategories = Category::active()->where('parent_id', $category)->where('parent_id','!=',0)->get();
    //     $keyword = $request->query('keyword', false);
    //     $key = explode(' ', $keyword);
    //     $products = Food::active()->
    //     when($category, function($query)use($category){
    //         $query->whereHas('category',function($q)use($category){
    //             return $q->whereId($category)->orWhere('parent_id', $category);
    //         });
    //     })
    //     ->when($keyword, function($query)use($key){
    //         return $query->where(function ($q) use ($key) {
    //             foreach ($key as $value) {
    //                 $q->orWhere('name', 'like', "%{$value}%");
    //             }
    //         });
    //     })->available($time)
    //     ->latest()->paginate(10);

    //     return view('vendor-views.pos.index-new', compact('categories','subcategories', 'products','category', 'keyword'));
    // }

    public function indexNew(Request $request)
    {
        $time = Carbon::now()->toTimeString();
        $category = $request->query('category_id', 0);
        $subcategory = $request->query('subcategory_id', 0);
        $keyword = $request->query('keyword', false);
        $key = explode(' ', strtolower($keyword));

        $categories = Category::active()->where('parent_id', 0)->get();

        $subcategories = Category::active()
            ->where('parent_id', $category)
            ->where('parent_id', '!=', 0)
            ->get();

        $products = Food::active()
            ->when($category, function ($query) use ($category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->whereId($category)->orWhere('parent_id', $category);
                });
            })
            ->when($subcategory, function ($query) use ($subcategory) {
                $query->whereHas('category', function ($q) use ($subcategory) {
                    $q->whereId($subcategory);
                });
            })
            ->when($keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$value}%"]);
                    }
                });
            })
            //    ->available($time)
            ->latest()->get();
        // ->paginate(10);

        if ($request->ajax()) {
            return response()->json([
                'subcategoryHtml' => view('vendor-views.pos._subcategory_list', compact('subcategories'))->render(),
                'productHtml' => view('vendor-views.pos._product_list', compact('products'))->render(),
            ]);
        }

        $editingOrderId = session('editing_order_id');
        $draftDetails = null;
        $editingOrder = null;
        $draftCustomer = null;

        if ($editingOrderId) {
            $draftDetails = PosOrderAdditionalDtl::where('order_id', $editingOrderId)->first();
            $editingOrder = Order::find($editingOrderId);

            if ($editingOrder && $editingOrder->user_id) {
                $draftCustomer = SaleCustomer::where('customer_id', $editingOrder->user_id)->first();
            }
        }

        $branchId = Helpers::get_restaurant_id();
        $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
        $orderDate = $branch ? $branch->orders_date : null;

        return view('vendor-views.pos.index-new', compact('categories', 'subcategories', 'products', 'category', 'subcategory', 'keyword', 'draftDetails', 'editingOrder', 'orderDate', 'draftCustomer'));
    }



    public function quick_view(Request $request)
    {
        $product = Food::findOrFail($request->product_id);
        // dd('variations: ' . $product->variations);
        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-data', compact('product'))->render(),
        ]);
    }

    public function quick_view_card_item(Request $request)
    {
        $product = Food::findOrFail($request->product_id);
        $item_key = $request->item_key;
        $cart_item = session()->get('cart')[$item_key];
        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key'))->render(),
        ]);
    }

    // public function variant_price(Request $request)
    // {
    //     $product = Food::find($request->id);
    //     $price = $product->price;
    //     $addon_price = 0;
    //     $add_on_ids=[];
    //     $add_on_qtys=[];
    //     if ($request['addon_id']) {
    //         foreach ($request['addon_id'] as $id) {
    //             $add_on_ids[]= $id;
    //             $add_on_qtys[]= $request['addon-quantity' . $id];
    //             $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
    //         }
    //     }
    //     $addonAndVariationStock= Helpers::addonAndVariationStockCheck(product:$product, quantity: $request->quantity,add_on_qtys:$add_on_qtys, variation_options:explode(',',$request->option_ids),add_on_ids:$add_on_ids );
    //     if(data_get($addonAndVariationStock, 'out_of_stock') != null) {
    //         return response()->json([
    //             'error' => 'stock_out',  'message' => data_get($addonAndVariationStock, 'out_of_stock'),
    //             'current_stock' => data_get($addonAndVariationStock, 'current_stock'),
    //             'id'=> data_get($addonAndVariationStock, 'id'),
    //             'type'=> data_get($addonAndVariationStock, 'type'),
    //         ],203);
    //     }

    //     $product_variations = json_decode($product->variations, true);
    //     if ($request->variations && count($product_variations)) {
    //         $price_total =  $price + Helpers::variation_price(product:$product_variations,variations: $request->variations);
    //         $price= $price_total - Helpers::product_discount_calculate(product:$product, price:$price_total, restaurant:Helpers::get_restaurant_data());
    //     } else {
    //         $price = $product->price - Helpers::product_discount_calculate(product:$product, price:$product->price, restaurant:Helpers::get_restaurant_data());
    //     }
    //     return array('price' => Helpers::format_currency(($price * $request->quantity) + $addon_price));
    // }


    public function variant_price(Request $request)
    {
        $product = Food::find($request->id);
        $price = $product->price;
        $addon_price = 0;
        $add_on_ids = [];
        $add_on_qtys = [];

        if ($request['addon_id']) {
            foreach ($request['addon_id'] as $id) {
                $add_on_ids[] = $id;
                $add_on_qtys[] = $request['addon-quantity' . $id];
                $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
            }
        }

        if ($request->has('variation_addon_id')) {
            foreach ($request->variation_addon_id as $variation_key => $addon_ids) {
                if (is_array($addon_ids)) {
                    foreach ($addon_ids as $addon_id) {
                        $quantity = $request->input("variation_addon_quantity.{$variation_key}.{$addon_id}", 1);
                        $addon_price_value = $request->input("variation_addon_price.{$variation_key}.{$addon_id}", 0);
                        $addon_price += $addon_price_value * $quantity;
                    }
                }
            }
        }

        $variation_options = null;
        if (isset($request->option_ids) && is_array($request->option_ids)) {
            $variation_options = explode(',', $request->option_ids);
        }
        $addonAndVariationStock = Helpers::addonAndVariationStockCheck($product, $request->quantity, $add_on_qtys, $variation_options, $add_on_ids);
        if (data_get($addonAndVariationStock, 'out_of_stock') != null) {
            return response()->json([
                'error' => 'stock_out',
                'message' => data_get($addonAndVariationStock, 'out_of_stock'),
                'current_stock' => data_get($addonAndVariationStock, 'out_of_stock'),
                'id' => data_get($addonAndVariationStock, 'id'),
                'type' => data_get($addonAndVariationStock, 'type'),
            ], 203);
        }

        $product_variations = json_decode($product->variations, true);

        if ($request->variations && is_array($request->variations) && count($request->variations) > 0 && count($product_variations) > 0) {
            $price_total = $price + Helpers::variation_price($product_variations, $request->variations);
        } else {
            $price_total = $price;
        }

        $original_price = $price_total;

        if ($request->product_discount_type && $request->has('product_discount')) {
            $discountAmount = $request->product_discount;
            $discountType = $request->product_discount_type;

            if ($discountType === 'percent') {
                $price_total -= ($price_total * $discountAmount) / 100;
            } elseif ($discountType === 'amount') {
                $price_total -= $discountAmount;
            }
        } else {
            // Apply restaurant discount (if any)
            $price_total = $price_total - Helpers::product_discount_calculate($product, $price_total, Helpers::get_restaurant_data());
        }

        $total_price = ($price_total * $request->quantity) + $addon_price;

        return response()->json([
            'price' => Helpers::format_currency($total_price),
            'original_price' => Helpers::format_currency($original_price),
            'pre_addon_price' => Helpers::format_currency($price_total),
        ]);
    }



    public function addDeliveryInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'nullable',
            'contact_person_number' => 'nullable',
            'floor' => 'nullable',
            'road' => 'nullable',
            'house' => 'nullable',
            'delivery_fee' => 'nullable',
            'longitude' => 'nullable',
            'latitude' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'delivery',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'delivery_fee' => $request->delivery_fee,
            'distance' => $request->distance,
            'longitude' => (string) $request->longitude,
            'latitude' => (string) $request->latitude,
        ];

        $request->session()->put('address', $address);

        return response()->json([
            'data' => $address,
            'view' => view('vendor-views.pos._address', compact('address'))->render(),
        ]);
    }

    public function addToCart(Request $request)
    {
        $product = Food::find($request->id);

        $data = array();
        $data['id'] = $product->id;
        $str = '';
        $variations = [];
        $price = 0;
        $addon_price = 0;
        $variation_price = 0;
        $add_on_ids = [];
        $add_on_qtys = [];
        $data['details'] = $request->notes;

        $product_variations = json_decode($product->variations, true);
        if ($request->variations && count($product_variations)) {
            foreach ($request->variations as $key => $value) {

                if ($value['required'] == 'on' && isset($value['values']) == false) {
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Please select items from') . ' ' . $value['name'],
                    ]);
                }
                if (isset($value['values']) && $value['min'] != 0 && $value['min'] > count($value['values']['label'])) {
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Please select minimum ') . $value['min'] . translate(' For ') . $value['name'] . '.',
                    ]);
                }
                if (isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])) {
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Please select maximum ') . $value['max'] . translate(' For ') . $value['name'] . '.',
                    ]);
                }
            }
            $variation_data = Helpers::get_varient($product_variations, $request->variations);
            $variation_price = $variation_data['price'];
            $variations = $request->variations;

            if ($request->has('variation_addon_id')) {
                foreach ($variations as $key => $variation) {
                    if (isset($request->variation_addon_id[$key]) && is_array($request->variation_addon_id[$key])) {
                        $variations[$key]['addons'] = [];
                        foreach ($request->variation_addon_id[$key] as $addon_id) {
                            $quantity = $request->input("variation_addon_quantity.{$key}.{$addon_id}", 1);
                            $price = $request->input("variation_addon_price.{$key}.{$addon_id}", 0);

                            $variations[$key]['addons'][] = [
                                'id' => $addon_id,
                                'name' => \App\Models\AddOn::find($addon_id)->name ?? '',
                                'price' => $price,
                                'quantity' => $quantity
                            ];

                            $addon_price += $price * $quantity;
                        }
                    }
                }
            }
        }

        $data['variations'] = $variations;
        $data['variant'] = $str;

        $price = $product->price + $variation_price;
        $data['variation_price'] = $variation_price;

        $data['quantity'] = $request['quantity'];
        $data['price'] = $price;
        $data['name'] = $product->name;
        if ($request->product_discount_type && $request->product_discount) {
            $discountAmount = $request->product_discount;
            $discountType = $request->product_discount_type;

            if ($discountType === 'percent') {
                $data['discount'] = ($price * $discountAmount) / 100;
            } elseif ($discountType === 'amount') {
                $data['discount'] = $discountAmount;
            }
            $data['discountAmount'] = $discountAmount;
            $data['discountType'] = $discountType;
        } else {
            $data['discount'] = Helpers::product_discount_calculate($product, $price, Helpers::get_restaurant_data());
        }
        $data['image'] = $product->image;
        $data['image_full_url'] = $product->image_full_url;
        $data['add_ons'] = [];
        $data['add_on_qtys'] = [];
        $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;
        $data['variation_option_ids'] = $request->option_ids ?? null;
        if ($request['addon_id']) {
            foreach ($request['addon_id'] as $id) {
                $add_on_ids[] = $id;
                $add_on_qtys[] = $request['addon-quantity' . $id];
                $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                $data['add_on_qtys'][] = $request['addon-quantity' . $id];
            }
            $data['add_ons'] = $request['addon_id'];
        }

        $all_addon_ids = $add_on_ids;
        $all_addon_qtys = $add_on_qtys;

        if ($request->has('variation_addon_id')) {
            foreach ($request->variation_addon_id as $variation_key => $addon_ids) {
                if (is_array($addon_ids)) {
                    foreach ($addon_ids as $addon_id) {
                        $quantity = $request->input("variation_addon_quantity.{$variation_key}.{$addon_id}", 1);
                        $all_addon_ids[] = $addon_id;
                        $all_addon_qtys[] = $quantity;
                    }
                }
            }
        }

        $addonAndVariationStock = Helpers::addonAndVariationStockCheck($product, $request->quantity, $all_addon_qtys, explode(',', $request->option_ids), $all_addon_ids);
        if (data_get($addonAndVariationStock, 'out_of_stock') != null) {
            return response()->json([
                'data' => 'stock_out',
                'message' => data_get($addonAndVariationStock, 'out_of_stock'),
                'current_stock' => data_get($addonAndVariationStock, 'current_stock'),
                'id' => data_get($addonAndVariationStock, 'id'),
                'type' => data_get($addonAndVariationStock, 'type'),
            ], 203);
        }

        $data['addon_price'] = $addon_price;

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if (isset($request->cart_item_key)) {
                $cart[$request->cart_item_key] = $data;
                $data = 2;
            } else {
                $cart->push($data);
            }
        } else {
            $cart = collect([$data]);
            $request->session()->put('cart', $cart);
        }

        return response()->json([
            'data' => $data
        ]);
    }

    public function cart_items()
    {
        $editingOrderId = session('editing_order_id');
        $draftDetails = null;
        $editingOrder = null;

        if ($editingOrderId) {
            $draftDetails = PosOrderAdditionalDtl::where('order_id', $editingOrderId)->first();
            $editingOrder = Order::find($editingOrderId);
        }
        return view('vendor-views.pos._cart', compact('draftDetails', 'editingOrder'));
    }

    public function removeFromCart(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $cart->forget($request->key);
            $request->session()->put('cart', $cart);
        }

        return response()->json([], 200);
    }

    public function updateQuantity(Request $request)
    {
        $product = Food::find($request->food_id);
        if ($request->option_ids) {
            $addonAndVariationStock = Helpers::addonAndVariationStockCheck($product, $request->quantity, explode(',', $request->option_ids));
            if (data_get($addonAndVariationStock, 'out_of_stock') != null) {
                return response()->json([
                    'data' => 'stock_out',
                    'message' => data_get($addonAndVariationStock, 'out_of_stock'),
                    'current_stock' => data_get($addonAndVariationStock, 'current_stock'),
                    'id' => data_get($addonAndVariationStock, 'id'),
                    'type' => data_get($addonAndVariationStock, 'type'),
                ], 203);
            }
        }

        $cart = $request->session()->get('cart', collect([]));
        $cart = $cart->map(function ($object, $key) use ($request) {
            if ($key == $request->key) {
                $object['quantity'] = $request->quantity;
            }
            return $object;
        });
        $request->session()->put('cart', $cart);
        return response()->json([], 200);
    }

    public function emptyCart(Request $request)
    {
        session()->forget('cart');
        session()->forget('address');
        session()->forget('editing_order_id');
        return response()->json([], 200);
    }

    public function update_tax(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['tax'] = $request->tax;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_discount(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));

        $subtotal = 0;
        $addon_price = 0;
        $discount_on_product = 0;

        foreach ($cart as $key => $cartItem) {
            if (is_array($cartItem)) {
                $product_subtotal = $cartItem['price'] * $cartItem['quantity'];
                $discount_on_product += $cartItem['discount'] * $cartItem['quantity'];
                $subtotal += $product_subtotal;
                $addon_price += $cartItem['addon_price'];
            }
        }

        $total = $subtotal + $addon_price;

        $discount = $request->discount;
        $discount_type = $request->type;
        $discount_amount = $discount_type == 'percent' && $discount > 0
            ? (($total - $discount_on_product) * $discount) / 100
            : $discount;

        $final_total = $total - $discount_amount - $discount_on_product;

        if ($final_total < 0) {
            Toastr::error(translate('messages.discount_cannot_exceed_order_amount'));
            return back();
        }

        $cart['discount'] = $request->discount;
        $cart['discount_type'] = $request->type;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_delivery_fee(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['delivery_fee'] = $request->delivery_fee;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_paid(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['paid'] = $request->paid;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function get_customers(Request $request)
    {
        try {
            $key = explode(' ', $request['q']);

            $data = SaleCustomer::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere(DB::raw('UPPER(customer_name)'), 'like', strtoupper("%{$value}%"))
                        ->orWhere(DB::raw('UPPER(customer_mobile_no)'), 'like', strtoupper("%{$value}%"))
                        ->orWhere(DB::raw('UPPER(customer_email)'), 'like', strtoupper("%{$value}%"));
                }
            })
                ->limit(8)
                ->get([DB::raw('customer_id as id'), DB::raw('customer_name'), DB::raw('customer_mobile_no')]);

            // Format the data for select2
            $formattedData = $data->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'text' => $customer->customer_name . ' (' . $customer->customer_mobile_no . ')'
                ];
            });

            $formattedData[] = (object) ['id' => false, 'text' => translate('messages.walk_in_customer')];

            return response()->json($formattedData);
        } catch (\Exception $e) {
            return response()->json([
                (object) ['id' => false, 'text' => translate('messages.walk_in_customer')]
            ]);
        }
    }

    public function place_order(Request $request)
    {
        $activeSession = \App\Models\ShiftSession::current()->first();
        if (!$activeSession) {
            Toastr::error('No active shift session found. Please start a shift session before placing orders.');
            return back();
        }

        $cart = $request->session()->get('cart');

        // dd($cart);
        $allNotes = [];
        foreach ($cart as $item) {
            $notes = $item['details'] ?? null; // Change 'notes' to 'details'
            $allNotes[] = $notes;
        }
        // dd($allNotes); // Now 'details' should be correctly pushed

        // if(!$request->type){
        //     Toastr::error(translate('No payment method selected'));
        //     return back();
        // }

        // If no amount is provided at all (neither cash nor card)
        if (
            ($request->cash_paid === null || $request->cash_paid < 0) &&
            ($request->card_paid === null || $request->card_paid < 0)
        ) {
            Toastr::error(translate('Payment amount cannot be negative'));
            return back();
        }

        $payment_type = '';
        if ($request->order_draft == 'final') {

            if ($request->cash_paid > 0 && ($request->card_paid === null || $request->card_paid <= 0)) {
                $payment_type = 'cash';
            } elseif ($request->card_paid > 0 && ($request->cash_paid === null || $request->cash_paid <= 0)) {
                $payment_type = 'card';
            } elseif ($request->cash_paid > 0 && $request->card_paid > 0) {
                $payment_type = 'cash_card';
            }
        }

        if ($request->session()->has('cart')) {
            if (count($request->session()->get('cart')) < 1) {
                Toastr::error(translate('messages.cart_empty_warning'));
                return back();
            }
        } else {
            Toastr::error(translate('messages.cart_empty_warning'));
            return back();
        }
        if ($request->session()->has('address')) {
            if (!$request->user_id) {
                Toastr::error(translate('messages.no_customer_selected'));
                return back();
            }
            $address = $request->session()->get('address');
        }
        $restaurant = Helpers::get_restaurant_data();
        $self_delivery_status = $restaurant->self_delivery_system;

        $rest_sub = $restaurant->restaurant_sub;
        if ($restaurant->restaurant_model == 'subscription' && isset($rest_sub)) {
            $self_delivery_status = $rest_sub->self_delivery;
            if ($rest_sub->max_order != "unlimited" && $rest_sub->max_order <= 0) {
                Toastr::error(translate('messages.You_have_reached_the_maximum_number_of_orders'));
                return back();
            }
        } elseif ($restaurant->restaurant_model == 'unsubscribed') {
            Toastr::error(translate('messages.You_are_not_subscribed_or_your_subscription_has_expired'));
            return back();
        }


        $cart = $request->session()->get('cart');

        $total_addon_price = 0;
        $product_price = 0;
        $restaurant_discount_amount = 0;

        $order_details = [];

        $editing_order_id = session('editing_order_id');
        if ($editing_order_id) {
            $order = Order::find($editing_order_id);
            if (!$order || $order->payment_status != 'unpaid') {
                Toastr::error('Invalid or already paid order.');
                return back();
            }
        } else {
            $order = new Order();
            $order->id = Helpers::generateGlobalId($restaurant->id);

        $branchId = Helpers::get_restaurant_id();
        $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
        $orderDate = $branch ? $branch->orders_date : null;
        $order->order_date = $orderDate;

            $today = Carbon::today();
            $todayOrderCount = Order::whereDate('created_at', $today)->count();

            $dayPart = $today->format('d');
            $sequencePart = str_pad($todayOrderCount + 1, 3, '0', STR_PAD_LEFT);
            $order->order_serial = "{$dayPart}-{$sequencePart}";

            $order->created_at = now();
            $order->schedule_at = now();
        }
        // $order->payment_status = isset($address) ? 'unpaid' : 'paid';
        $order->payment_status = $request->order_draft == 'draft' ? 'unpaid' : 'paid';
        if (!$editing_order_id) {
            $order->kitchen_status = 'pending';
            $order->order_status = $order->kitchen_status;
        }

        $order->order_type = $request->delivery_type ?? 'delivery';

        $order->delivered = $order->order_status == 'delivered' ? now() : null;
        $order->distance = isset($address) ? $address['distance'] : 0;

        $distance_data = $order->distance ?? 1;
        $extra_charges = 0;
        if ($self_delivery_status != 1) {
            $data = Helpers::vehicle_extra_charge($distance_data);
            $vehicle_id = (isset($data) ? $data['vehicle_id'] : null);
            $extra_charges = (float) (isset($data) ? $data['extra_charge'] : 0);
        }
        $additional_charge_status = BusinessSetting::where('key', 'additional_charge_status')->first()->value;
        $additional_charge = BusinessSetting::where('key', 'additional_charge')->first()->value;
        if ($additional_charge_status == 1) {
            $order->additional_charge = $additional_charge ?? 0;
        } else {
            $order->additional_charge = 0;
        }


        $order->vehicle_id = $vehicle_id ?? null;
        $order->restaurant_id = $restaurant->id;
        $order->user_id = $request->user_id;
        $order->order_taken_by = Auth::guard('vendor_employee')->user()->id ?? '';
        $order->zone_id = $restaurant->zone_id;
        $order->session_id = $activeSession->session_id;
        $order->delivery_charge = isset($address) ? $address['delivery_fee'] : 0;
        $order->delivery_charge += isset($cart['delivery_fee']) ? $cart['delivery_fee'] : 0;
        // $order->original_delivery_charge = isset($address) ? $address['delivery_fee'] : 0;
        $order->original_delivery_charge = 0;
        $order->delivery_address = isset($address) ? json_encode($address) : null;
        $order->checked = 1;
        $order->updated_at = now();
        $order->otp = rand(1000, 9999);

        DB::beginTransaction();
        foreach ($cart as $c) {

            if (is_array($c)) {
                $product = Food::find($c['id']);
                if ($product) {
                    $price = $c['price'];
                    $product->tax = $restaurant->tax;
                    $product = Helpers::product_data_formatting($product);
                    $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::whereIn('id', $c['add_ons'])->get(), $c['add_on_qtys']);

                    $all_addon_ids = $c['add_ons'] ?? [];
                    $all_addon_qtys = $c['add_on_qtys'] ?? [];

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

                    if (data_get($c, 'variation_option_ids') || count($all_addon_ids) > 0) {
                        $addonAndVariationStock = Helpers::addonAndVariationStockCheck($product, $c['quantity'], $all_addon_qtys, explode(',', data_get($c, 'variation_option_ids')), $all_addon_ids, true);

                        if (data_get($addonAndVariationStock, 'out_of_stock') != null) {
                            Toastr::error(data_get($addonAndVariationStock, 'out_of_stock'));
                            return back()->withInput();
                        }
                    }
                    if (data_get($c, 'variation_option_ids') == null) {
                        $product->increment('sell_count', $c['quantity']);
                    }

                    $variation_data = Helpers::get_varient($product->variations, $c['variations']);
                    $processed_variations = $variation_data['variations'];

                    $total_addon_price_for_item = $addon_data['total_add_on_price'];

                    if (isset($c['variations']) && is_array($c['variations'])) {
                        foreach ($c['variations'] as $variation) {
                            if (isset($variation['addons']) && is_array($variation['addons'])) {
                                foreach ($variation['addons'] as $addon) {
                                    $total_addon_price_for_item += ($addon['price'] * $addon['quantity']);
                                }
                            }
                        }
                    }

                    $complete_variations = [];
                    if (isset($c['variations']) && is_array($c['variations'])) {
                        foreach ($c['variations'] as $key => $cartVariation) {
                            $completeVariation = isset($processed_variations[$key]) ? $processed_variations[$key] : $cartVariation;

                            if (isset($cartVariation['addons'])) {
                                $completeVariation['addons'] = $cartVariation['addons'];
                            }

                            $complete_variations[] = $completeVariation;
                        }
                    }

                    $or_d = [
                        'food_id' => $c['id'],
                        'food_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => Helpers::tax_calculate($product, $price),
                        'discount_on_food' => $c['discount'],
                        'discount_type' => 'discount_on_product',
                        'variation' => json_encode($complete_variations), // Use complete variations with addons
                        'add_ons' => json_encode($addon_data['addons']),
                        'total_add_on_price' => $total_addon_price_for_item,
                        'notes' => $c['notes'] ?? $c['details'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    error_log('Cart variations structure: ' . json_encode($c['variations']));
                    error_log('Cart variations keys: ' . json_encode(array_keys($c['variations'] ?? [])));
                    if (isset($c['variations'][0]['values'])) {
                        error_log('First variation values: ' . json_encode($c['variations'][0]['values']));
                    }
                    error_log('Order detail variation field: ' . $or_d['variation']);
                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price * $or_d['quantity'];
                    $restaurant_discount_amount += $or_d['discount_on_food'] * $or_d['quantity'];
                    $order_details[] = $or_d;
                }
            }
        }

        $order->discount_on_product_by = 'vendor';
        $restaurant_discount = Helpers::get_restaurant_discount($restaurant);
        if (isset($restaurant_discount)) {
            $order->discount_on_product_by = 'admin';
        }
        if (isset($cart['discount'])) {
            $restaurant_discount_amount += $cart['discount_type'] == 'percent' && $cart['discount'] > 0 ? ((($product_price + $total_addon_price - $restaurant_discount_amount) * $cart['discount']) / 100) : $cart['discount'];
        }

        $total_price = $product_price + $total_addon_price - $restaurant_discount_amount;
        $tax = isset($cart['tax']) ? $cart['tax'] : $restaurant->tax;

        $order->tax_status = 'excluded';

        $tax_included = BusinessSetting::where(['key' => 'tax_included'])->first()->value ?? 0;
        if ($tax_included == 1) {
            $order->tax_status = 'included';
        }

        $total_tax_amount = Helpers::product_tax($total_price, $tax, $order->tax_status == 'included');
        $tax_a = $order->tax_status == 'included' ? 0 : $total_tax_amount;
        try {
            $order->restaurant_discount_amount = $restaurant_discount_amount;
            $order->total_tax_amount = $total_tax_amount;

            $order->order_amount = $total_price + $tax_a + $order->delivery_charge + $order->additional_charge;
            $order->adjusment = $request->amount ?? $order->order_amount;
            // $order->payment_method = $request->type;
            $order->payment_method = $payment_type;


            $max_cod_order_amount_value = BusinessSetting::where('key', 'max_cod_order_amount')->first()->value ?? 0;
            if ($max_cod_order_amount_value > 0 && $order->payment_method == 'cash_on_delivery' && $order->order_amount > $max_cod_order_amount_value) {
                Toastr::error(translate('messages.You can not Order more then ') . $max_cod_order_amount_value . Helpers::currency_symbol() . ' ' . translate('messages.on COD order.'));
                return back();
            }

            $order->save();

            if (!$editing_order_id) {
                KitchenOrderStatusLog::create([
                    "status" => 'pending',
                    "order_id" => $order->id,
                    "id" => $order->id.'1',
                ]);
            }
            if ($editing_order_id) {
                OrderDetail::where('order_id', $order->id)->delete();
            }

            foreach ($order_details as $key => $item) {
                $order_details[$key]['id'] = $order->id.$key;
                $order_details[$key]['order_id'] = $order->id;
            }
            OrderDetail::insert($order_details);
            $posOrderDtl = PosOrderAdditionalDtl::firstOrNew(['order_id' => $order->id]);
            $posOrderDtl->id = $order->id.'1';
            $posOrderDtl->restaurant_id = $order->restaurant_id;
            $posOrderDtl->customer_name = $request->customer_name;
            $posOrderDtl->car_number = $request->car_number;
            $posOrderDtl->phone = $request->phone;
            $posOrderDtl->invoice_amount = $order->order_amount ?? 0;
            $posOrderDtl->cash_paid = $request->cash_paid ?? 0;
            $posOrderDtl->card_paid = $request->card_paid ?? 0;
            $posOrderDtl->bank_account = $request->bank_account;
            $posOrderDtl->order_notes = $request->order_notes;
            $posOrderDtl->save();

            session()->forget('cart');
            session()->forget('editing_order_id');
            session()->forget('address');
            session(['last_order' => $order->id]);

            if ($restaurant->restaurant_model == 'subscription' && isset($rest_sub)) {
                if ($rest_sub->max_order != "unlimited" && $rest_sub->max_order > 0) {
                    $rest_sub->decrement('max_order', 1);
                }
            }

            DB::commit();

            // Print order receipts
            try {
                $printController = new \App\Http\Controllers\PrintController();

                $printController->printOrder(new \Illuminate\Http\Request(['order_id' => (string) $order->id]));

               $printController->printOrderKitchen(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
                
               $order->printed = 1;
               $order->save();
            } catch (\Exception $printException) {
                info('Print error: ' . $printException->getMessage());
            }

            //PlaceOrderMail
            // try {
            //     $notification_status = Helpers::getNotificationStatusData('customer', 'customer_order_notification');

            //     if ($notification_status->mail_status == 'active' && $order->order_status == 'pending' && config('mail.status') && Helpers::get_mail_status('place_order_mail_status_user') == '1' && $order->customer->email) {
            //         Mail::to($order->customer->email)->send(new PlaceOrder($order->id));
            //     }
            // } catch (\Exception $exception) {
            //     info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            // }

            //PlaceOrderMail end

            if ($request->order_draft == 'draft') {
                Toastr::success(translate('messages.order_drafted_successfully'));
            } else {
                Toastr::success(translate('messages.order_placed_successfully'));
            }

            return back();
        } catch (\Exception $exception) {
            DB::rollBack();
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }

        Toastr::warning(translate('messages.failed_to_place_order'));
        return back();
    }

    public function loadDraftOrderToCart($order_id)
    {
        $order = Order::with('details')->find($order_id);

        if (!$order || $order->payment_status != 'unpaid') {
            Toastr::error('Only unpaid (draft) orders can be edited.');
            return back();
        }

        $cart = [];

        foreach ($order->details as $item) {
            $food = json_decode($item->food_details, true);

            $variation_price = 0;
            $variations = json_decode($item->variation, true) ?? [];

            foreach ($variations as $variation) {
                $variation_price += $variation['optionPrice'] ?? 0;
            }

            $simplified_variations = Helpers::simplifyVariationsToLabels($variations);
            $rawAddOns = json_decode($item->add_ons, true) ?? [];
            $addon_ids = collect($rawAddOns)->pluck('id')->toArray();
            $addon_qtys = collect($rawAddOns)->pluck('quantity')->toArray();

            $cart[] = [
                'id' => $item->food_id,
                'name' => $food['name'] ?? '',
                'quantity' => $item->quantity,
                'price' => $item->price + $variation_price,
                'variation_price' => $variation_price,
                'variant' => '',
                'variations' => $simplified_variations,
                'variation_option_ids' => '',
                'add_ons' => $addon_ids,
                'add_on_qtys' => $addon_qtys,
                'addon_price' => $item->total_add_on_price,
                'discount' => $item->discount_on_food,
                'discountAmount' => $item->discount_on_food,
                'discountType' => 'amount',
                'details' => $item->notes,
                'image' => $food['image'] ?? null,
                'image_full_url' => $food['image_full_url'] ?? null,
                'maximum_cart_quantity' => $food['maximum_cart_quantity'] ?? 1000,
            ];
        }

        $cartSession = collect($cart);

        if ($order->delivery_charge > 0) {
            $cartSession['delivery_fee'] = $order->delivery_charge;
        }

        if ($order->restaurant_discount_amount > 0) {
            $cartSession['discount'] = $order->restaurant_discount_amount;
            $cartSession['discount_type'] = 'amount';
        }

        if ($order->total_tax_amount > 0) {
            $subtotal = $order->order_amount - $order->total_tax_amount - $order->delivery_charge;
            if ($subtotal > 0) {
                $tax_percentage = ($order->total_tax_amount / $subtotal) * 100;
                $cartSession['tax'] = round($tax_percentage, 2);
            }
        }

        session()->put('cart', $cartSession);
        session()->put('editing_order_id', $order->id);

        if ($order->delivery_address) {
            $deliveryAddress = json_decode($order->delivery_address, true);
            if ($deliveryAddress && is_array($deliveryAddress)) {
                session()->put('address', $deliveryAddress);
            }
        }

        Toastr::success('Unpaid order loaded to cart.');
        return redirect()->route('vendor.pos.index.new');
    }


    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'nullable',
            'phone' => 'required',
            'email' => 'nullable|email',
        ]);
        $branchId = Helpers::get_restaurant_id();
        $customerName = $request['f_name'] ?? 'Customer';
        $customer = SaleCustomer::create([
            'customer_code' => SaleCustomer::generateCustomerCode(),
            'customer_type' => '10223122121801',
            'customer_name' => $customerName,
            'customer_mobile_no' => $request['phone'],
            'customer_email' => $request['email'],
            'business_id' => 1,
            'company_id' => 1,
            'branch_id' => $branchId,
            'customer_id' => SaleCustomer::generateCustomerId($branchId)
        ]);
        // try {
        //     $notification_status = Helpers::getNotificationStatusData('customer', 'customer_pos_registration');

        //     if ($notification_status->mail_status == 'active' && config('mail.status') && $request->email && Helpers::get_mail_status('pos_registration_mail_status_user') == '1') {
        //         Mail::to($request->email)->send(new \App\Mail\CustomerRegistrationPOS($request->f_name . ' ' . $request->l_name, $request['email'], 'password'));
        //         Toastr::success(translate('mail_sent_to_the_user'));
        //     }
        // } catch (\Exception $ex) {
        //     info($ex->getMessage());
        // }
        Toastr::success(translate('customer_added_successfully'));
        return back();
    }
    public function extra_charge(Request $request)
    {
        $distance_data = $request->distancMileResult ?? 1;
        $self_delivery_status = $request->self_delivery_status;
        $extra_charges = 0;
        if ($self_delivery_status != 1) {
            $data = Helpers::vehicle_extra_charge($distance_data);
            $vehicle_id = (isset($data) ? $data['vehicle_id'] : null);
            $extra_charges = (float) (isset($data) ? $data['extra_charge'] : 0);
        }
        return response()->json($extra_charges, 200);
    }


}
