<?php

namespace App\Http\Controllers\Vendor;

use Carbon\Carbon;
use App\Models\Food;
use App\Models\User;
use App\Models\AddOn;
use App\Models\Order;
use App\Events\myevent;
use App\Mail\PlaceOrder;
use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\SaleCustomer;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\OrderCancelReason;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\KitchenOrderStatusLog;
use App\Models\PosOrderAdditionalDtl;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

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

    public function indexNew(Request $request, $id=null)
    {
        $time = Carbon::now()->toTimeString();
        
        if(session('current_category_id') == null){
            session()->put('current_category_id', 17);
        }

        $category = $request->query('category_id', session('current_category_id', 0));
        $subcategory = $request->query('subcategory_id', session('current_sub_category_id', 0));
        $keyword = $request->query('keyword', false);
        $key = explode(' ', strtolower($keyword));

        // Store the current category and subcategory in session
        session()->put('current_category_id', $category);
        session()->put('current_sub_category_id', $subcategory);

        
        $categories = Category::active()->where('parent_id', 0)->get();

        $subcategories = Category::active()
            ->where('parent_id', $category)
            ->where('parent_id', '!=', 0)
            ->get();

        // Check the Order Partner ID and Manage Cart Session
        if($id != session()->get('current_partner_id', '')){
            // If the current partner ID in session is different from the new one

            session()->forget('cart'); // Clear the cart
            session()->put('current_partner_id', $id); // Set the new partner ID
        }

        $categoryIds = null;

        if (!empty($subcategory)) {
            $categoryIds = [$subcategory];
        } elseif (!empty($category)) {
            $categoryIds = \App\Models\Category::where('id', $category)
                ->orWhere('parent_id', $category)
                ->pluck('id')
                ->all();
        }

        $products = Food::active()
        ->when($categoryIds, fn($q) => $q->whereIn('category_id', $categoryIds))
        ->when(!empty($keyword), function ($q) use ($keyword) {
            $keys = is_array($keyword) ? $keyword : preg_split('/\s+/', trim($keyword));

            $q->where(function ($qq) use ($keys) {
                foreach ($keys as $value) {
                    $value = trim($value);
                    if ($value !== '') {
                        $qq->orWhere('name', 'LIKE', "%{$value}%"); // remove LOWER()
                    }
                }
            });
        })
        ->latest()
        ->get();
        
        if(!empty($id)){
            $products->each(function ($item) use ($id) {
                $partnerPrices = json_decode($item->partner_price, true) ?: [];

                foreach ($partnerPrices as $pp) {
                    if (($pp['partner_id'] ?? null) == $id) {
                        $item->price = $pp['price'] ?? $item->price;
                        break;
                    }
                }
            });
        }

        // dd('All Data Loaded');
        if ($request->ajax()) {
            return response()->json([
                'subcategoryHtml' => view('vendor-views.pos._subcategory_list', compact('subcategories'))->render(),
                'productHtml' => view('vendor-views.pos._product_list', compact('products'))->render(),
            ]);
        }

        $editingOrderId = session('editing_order_id');
        $draftDetails   = null;
        $editingOrder   = null;
        $draftCustomer  = null;
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
        $orderPartners = DB::table('tbl_sale_order_partners')->orderBy('created_at')->get();
        $orderPartner = $id;
        $bankaccounts = DB::table('tbl_defi_bank')->get();

        $previousDate = $orderDate ? Carbon::parse($orderDate) : null;
        $currentDate = Carbon::now();
        $updateDate = false;

        if ($previousDate && $previousDate->toDateString() != $currentDate->toDateString()
            && $currentDate->hour >= 8) {
            // Show warning
            $updateDate = true;
        }

        $reasons = OrderCancelReason::where('status', 1)->where('user_type', 'restaurant')->get();
        $cancelStatuses = ['Unprepared', 'Prepared', 'Wasted'];

        return view('vendor-views.pos.index-new', compact(
            'categories',
            'subcategories',
            'products',
            'category',
            'subcategory',
            'keyword',
            'draftDetails',
            'editingOrder',
            'orderDate',
            'draftCustomer',
            'orderPartners',
            'orderPartner',
            'bankaccounts',
            'updateDate',
            'reasons',
            'cancelStatuses'
        ));
    }

    public function quick_view(Request $request)
    {
        $product = Food::findOrFail($request->product_id);

        $partner_id = $request->id ?: null;

        if (!empty($partner_id)) {

            // 1) Partner base price from JSON
            $partner_prices = collect(json_decode($product->partner_price, true) ?? []);
            $partner_price_row = $partner_prices->firstWhere('partner_id', $partner_id);

            if (!empty($partner_price_row) && isset($partner_price_row['price'])) {
                $product->price = $partner_price_row['price'];
            }

            // 2) Decode variations once
            $variations = json_decode($product->variations, true) ?? [];

            // 3) Collect all option IDs in one pass
            $optionIds = [];

            foreach ($variations as $variation) {
                if (!empty($variation['values']) && is_array($variation['values'])) {
                    foreach ($variation['values'] as $v) {
                        if (!empty($v['option_id'])) {
                            $optionIds[] = $v['option_id'];
                        }
                    }
                }
            }

            // Avoid query if there are no options
            if (!empty($optionIds)) {
                // 4) Fetch all prices in a single query
                $prices = DB::table('PARTNER_VARIATION_OPTION')
                    ->where('is_deleted', 0)
                    ->where('partner_id', $partner_id)
                    ->whereIn('variation_option_id', $optionIds)
                    ->pluck('price', 'variation_option_id'); // [variation_option_id => price]

                // 5) Fill optionPrice from the map
                foreach ($variations as &$variation) {
                    if (!empty($variation['values']) && is_array($variation['values'])) {
                        foreach ($variation['values'] as &$v) {
                            $v['optionPrice'] = $prices[$v['option_id']] ?? null;
                        }
                    }
                }
                unset($variation, $v); // break references

                // 6) Save back to product
                $product->variations = json_encode($variations);
            }
        }

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-data', compact('product', 'partner_id'))->render(),
        ]);
    }


    public function quick_view_card_item(Request $request)
    {
        $product = Food::findOrFail($request->product_id);
        $item_key = $request->item_key;
        $cart_item = session()->get('cart')[$item_key];
        $editing_order_id = session()->get('editing_order_id') ?? null;
        
        if($editing_order_id){
            $orderPaymentStatus = Order::where('id', $editing_order_id)->first()->payment_status;
        }else{
            $orderPaymentStatus = 'unpaid';
        }

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key', 'editing_order_id','orderPaymentStatus'))->render(),
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
        $product = Food::select(['id', 'price', 'partner_price', 'variations', 'discount', 'discount_type'])
        ->findOrFail($request->id);

        if($request->filled('partner_id')){
            $partner_price = collect(json_decode($product->partner_price));
            $price = optional( $partner_price->where('partner_id',$request->partner_id)->first())->price;
        }else{
            $price = $product->price;
        }

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


            $price_total = $price + Helpers::variation_price($product_variations, $request->variations, $request->partner_id);
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
        $orderId = session('editing_order_id');

        // 1) Early check: cannot modify paid order
        if ($orderId) {
            $existingOrder = Order::find($orderId);
            if ($existingOrder && $existingOrder->payment_status == 'paid') {
                return response()->json([
                    'data' => 'not_allowed',
                    'message' => translate('Cannot modify a paid order. Please create a new order.'),
                ]);
            }
        }

        // 2) Load product once
        $product = Food::findOrFail($request->id);

        $data = [];
        $data['id'] = $product->id;
        $data['partner_id'] = $request->partner_id ?? '';
        $data['details'] = $request->notes;
        $data['variant'] = '';
        $data['is_deleted'] = 'N';
        $data['is_printed'] = $request->is_printed ?? 0;
        $data['image'] = $product->image;
        $data['image_full_url'] = $product->image_full_url;
        $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;
        $data['variation_option_ids'] = $request->option_ids ?? null;
        $data['options_changed'] = $request->options_changed ?? 0;

        $variations = [];
        $variation_price = 0;
        $addon_price = 0;

        $product_variations = json_decode($product->variations, true) ?? [];

        // 3) Validate and calculate variation price
        if ($request->variations && count($product_variations)) {
            foreach ($request->variations as $key => $value) {

                if ($value['required'] == 'on' && !isset($value['values'])) {
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

            $variation_data = Helpers::get_varient($product_variations, $request->variations, $data['partner_id']);
            $variation_price = $variation_data['price'];
            $variations = $request->variations;
        }

        // 4) Collect ALL addon IDs first (normal + variation addons) to avoid N+1

        $add_on_ids = [];
        $add_on_qtys = [];

        // Normal addons (non-variation)
        if ($request->has('addon_id')) {
            foreach ($request->addon_id as $id) {
                $add_on_ids[] = (int) $id;
                $qty = (int) $request->input('addon-quantity' . $id, 1);
                $price = (float) $request->input('addon-price' . $id, 0);
                $add_on_qtys[] = $qty;
                $addon_price += $price * $qty;
            }
        }

        // Variation addons
        if ($request->has('variation_addon_id')) {
            foreach ($request->variation_addon_id as $variation_key => $addon_ids_for_variation) {
                if (!is_array($addon_ids_for_variation)) {
                    continue;
                }

                foreach ($addon_ids_for_variation as $addon_id) {
                    $addon_id = (int) $addon_id;
                    $quantity = (int) $request->input("variation_addon_quantity.{$variation_key}.{$addon_id}", 1);
                    $unitPrice = (float) $request->input("variation_addon_price.{$variation_key}.{$addon_id}", 0);

                    $add_on_ids[] = $addon_id;
                    $add_on_qtys[] = $quantity;
                    $addon_price += $unitPrice * $quantity;
                }
            }
        }

        // 5) Preload all AddOn names in ONE query (for both normal + variation addons)
        $addonNames = [];
        if (!empty($add_on_ids)) {
            $addonNames = AddOn::whereIn('id', array_unique($add_on_ids))
                ->pluck('name', 'id')
                ->toArray();
        }

        // 6) Now rebuild variation addons with names (using in-memory $addonNames, no more AddOn::find inside loops)
        if ($request->has('variations') && $request->has('variation_addon_id')) {
            foreach ($variations as $key => &$variation) {
                if (isset($request->variation_addon_id[$key]) && is_array($request->variation_addon_id[$key])) {
                    $variation['addons'] = [];
                    foreach ($request->variation_addon_id[$key] as $addon_id) {
                        $addon_id = (int) $addon_id;
                        $quantity = (int) $request->input("variation_addon_quantity.{$key}.{$addon_id}", 1);
                        $unitPrice = (float) $request->input("variation_addon_price.{$key}.{$addon_id}", 0);

                        $variation['addons'][] = [
                            'id'       => $addon_id,
                            'name'     => $addonNames[$addon_id] ?? '',
                            'price'    => $unitPrice,
                            'quantity' => $quantity,
                        ];
                    }
                }
            }
            unset($variation); // break reference
        }

        $data['variations'] = $variations;

        // 7) Base/partner price calculation
        if (!empty($data['partner_id'])) {
            $partnerPrices = collect(json_decode($product->partner_price, true) ?? []);
            $partnerRow = $partnerPrices->firstWhere('partner_id', $data['partner_id']);
            $base_price = isset($partnerRow['price']) ? (float) $partnerRow['price'] : (float) $product->price;
        } else {
            $base_price = (float) $product->price;
        }

        $unit_base_plus_variation = $base_price + $variation_price;

        $data['variation_price'] = $variation_price;
        $data['addon_price'] = $addon_price;
        $data['quantity'] = (int) $request->quantity;
        $data['name'] = $product->name;

        // 8) Discount calculation (you can decide whether to include addons in discount)
        $unit_total_for_discount = $unit_base_plus_variation; // or + $addon_price if you want

        if ($request->product_discount_type && $request->product_discount) {
            $discountAmount = (float) $request->product_discount;
            $discountType = $request->product_discount_type;

            if ($discountType === 'percent') {
                $data['discount'] = ($unit_total_for_discount * $discountAmount) / 100;
            } elseif ($discountType === 'amount') {
                $data['discount'] = $discountAmount;
            }

            $data['discountAmount'] = $discountAmount;
            $data['discountType'] = $discountType;
        } else {
            $restaurantData = Helpers::get_restaurant_data(); // called once
            $data['discount'] = Helpers::product_discount_calculate(
                $product,
                $unit_total_for_discount,
                $restaurantData
            );
        }

        // Decide what you want to store as unit "price"
        $data['price'] = $unit_base_plus_variation; // keeping your original meaning

        // 9) Store normal addons explicitly in $data if needed
        $data['add_ons'] = $request->addon_id ?? [];
        $data['add_on_qtys'] = $add_on_qtys;

        // 10) Stock check using already collected IDs and qtys
        $optionIds = $request->option_ids ? explode(',', $request->option_ids) : [];

        $addonAndVariationStock = Helpers::addonAndVariationStockCheck(
            $product,
            $data['quantity'],
            $add_on_qtys,
            $optionIds,
            $add_on_ids
        );

        if (data_get($addonAndVariationStock, 'out_of_stock') !== null) {
            return response()->json([
                'data'          => 'stock_out',
                'message'       => data_get($addonAndVariationStock, 'out_of_stock'),
                'current_stock' => data_get($addonAndVariationStock, 'current_stock'),
                'id'            => data_get($addonAndVariationStock, 'id'),
                'type'          => data_get($addonAndVariationStock, 'type'),
            ], 203);
        }

        // 11) Set addon total price on data
        $data['addon_price'] = $addon_price;

        // 12) Cart handling logic (unchanged, just reused)
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));

            if (isset($request->cart_item_key)) {
                $currentItemInCart = $cart[$request->cart_item_key];
                $currentQty = $currentItemInCart['quantity'];
                $newQty = $data['quantity'];

                if($orderId){
                    $currentItemPrice = (($currentItemInCart['price'] * $currentItemInCart['quantity']) - $currentItemInCart['discount']) + $currentItemInCart['addon_price'];

                    $newItemPrice = (($data['price'] * $data['quantity']) - $data['discount']) + $data['addon_price'];
                    
                    if($newItemPrice < $currentItemPrice){
                        return response()->json([
                            'data' => 'price_updation_error',
                            'message' => translate("messages.For_existing_orders_item_price_can_not_be_reduced."),
                        ]);
                    }
                }
            
                if ($newQty < $currentQty) {
                    if (!session('editing_order_id')) {
                        $cart[$request->cart_item_key] = $data;
                    } else {
                        $differenceInQty = $currentQty - $newQty;
                        $data['draft_product'] = true;
                        $cart[$request->cart_item_key] = $data;

                        $currentItemInCart['quantity'] = $differenceInQty;
                        $currentItemInCart['is_deleted'] = 'Y';
                        $currentItemInCart['cancel_reason'] = $data['cancel_reason'] ?? '1';
                        $currentItemInCart['cooking_status'] = $data['cooking_status'] ?? '1';
                        $currentItemInCart['cancel_text'] = 'Quantity Reduced from POS';

                        $cart->push($currentItemInCart);
                    }
                } else {
                    foreach ($cart as $key => $item) {
                        if ($item['id'] == $data['id'] && $item['is_deleted'] == 'Y' && $key != $request->cart_item_key) {
                            $itemQty = $item['quantity'];
                            $requiredQty = $newQty;
                            $sum = $currentQty + $itemQty;

                            if ($sum == $requiredQty) {
                                $cart->forget($key);
                            } elseif ($sum > $requiredQty) {
                                $item['quantity'] = $sum - $requiredQty;
                                $cart[$key] = $item;
                            } else {
                                $cart->forget($key);
                            }
                        }
                    }

                    $cart[$request->cart_item_key] = $data;
                }

                $data = 2;
            } else {
                $cart->push($data);
            }
        } else {
            $cart = collect([$data]);
            $request->session()->put('cart', $cart);
        }

        return response()->json([
            'data' => $data,
        ]);
    }


    public function cart_items(Request $request)
    {
        $editingOrderId = session('editing_order_id');
        $editingOrder   = null;
        $draftDetails   = null;

        $orderPartner = $request->partner_id ?? '';

        // 1) Cache bank accounts per branch
        $branchId = Helpers::get_restaurant_id();

        $bankaccounts = DB::table('tbl_defi_bank')
                    ->get();

        // 2) Load editing order + draft detail in one go if needed
        if ($editingOrderId) {
            $editingOrder = Order::with('posAdditionalDetail')->find($editingOrderId);
            $draftDetails = $editingOrder ? $editingOrder->posAdditionalDetail : null;
        }

        return view('vendor-views.pos._cart', compact(
            'draftDetails',
            'editingOrder',
            'orderPartner',
            'bankaccounts'
        ));
    }

    public function removeFromCart(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $editing_order_id = session('editing_order_id');
            if ($editing_order_id) {
                // 1. Get the existing order payment status
                $existingPaymentStatus = Order::find($editing_order_id)->payment_status ?? null;

                // 2. Get item from cart FIRST
                $cartItem = $cart->get($request->key);

                if (!$cartItem) {
                    Toastr::error('Cart item not found');
                    return back();
                }

                // 3. If order was already paid — lock item payment_status
                if ($existingPaymentStatus === 'paid') {
                    $cartItem['payment_status'] = 'paid';
                }

                // 4. Mark item as deleted + store details
                $cartItem['is_deleted']     = 'Y';
                $cartItem['cancel_reason']  = $request->cancelReason ?? '';
                $cartItem['cooking_status'] = $request->cookingStatus ?? '';
                $cartItem['cancel_text']    = $request->cancelText ?? '';

                // 5. Put updated item back into cart
                $cart->put($request->key, $cartItem);
            } else {
                // For non-editing orders: remove the item fully
                $cart->forget($request->key);
            }

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
        // session()->forget('bank_account');
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
            $key = str_replace(' ', '%', $request['q']);

            $data = SaleCustomer::where(function ($query) use ($key) {
                $query->orWhere(DB::raw('UPPER(customer_name)'), 'like', strtoupper("{$key}%"))
                    ->orWhere(DB::raw('UPPER(customer_mobile_no)'), 'like', strtoupper("{$key}%"))
                    ->orWhere(DB::raw('UPPER(customer_email)'), 'like', strtoupper("{$key}%"));

            })
            ->limit(8)
            ->orderBy('customer_code', 'desc')
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
        $activeSession = \App\Models\ShiftSession::current()
        ->where('user_id', auth('vendor')->id() ?? auth('vendor_employee')->id())
        ->first();
        if (!$activeSession) {
            Toastr::error('No active shift session found. Please start a shift session with your ID before placing orders.');
            return back();
        }

        $cart = $request->session()->get('cart');

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

        $payment_type = '';
        if ($request->order_draft == 'final') {

            // Normalize values once
            $cash = floatval($request->cash_paid ?? 0);
            $card = floatval($request->card_paid ?? 0);
            $isCredit = isset($request->select_payment_type) 
                && $request->select_payment_type == 'credit_payment';

            // 1) Block negative payments (only for non-credit payments)
            if (!$isCredit && ($cash < 0 || $card < 0)) {
                Toastr::error(translate('Payment amount cannot be negative'));
                return back();
            }

            // 2) Payment type decision
            if ($isCredit) {
                // (Optional but recommended)
                // If you don't want any paid amounts when credit is chosen:
                if ($cash > 0 || $card > 0) {
                    Toastr::error(translate('For credit payment, cash or card must be zero'));
                    return back();
                }

                $payment_type = 'credit';

            } else {

                if ($cash > 0 && $card <= 0) {
                    $payment_type = 'cash';
                } elseif ($card > 0 && $cash <= 0) {
                    $payment_type = 'card';
                } elseif ($cash > 0 && $card > 0) {
                    $payment_type = 'cash_card';
                } else {
                    $payment_type = 'cash'; // fallback when everything is zero
                }
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
        $discount_on_product = 0;
        $restaurant_discount_amount = 0;

        $order_details = [];

        $editing_order_id = session('editing_order_id');
        $oldVariationJson = '';
        if ($editing_order_id) {
            $order = Order::find($editing_order_id);
            // if (!$order || $order->payment_status != 'unpaid') {
            //     Toastr::error('Invalid or already paid order.');
            //     return back();
            // }
        } else {
            $order = new Order();
            $order->id = Helpers::generateGlobalId($restaurant->id);

            $branchId = Helpers::get_restaurant_id();
            $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
            $orderDate = $branch ? $branch->orders_date : null;
            $order->order_date = $orderDate;

            $today = Carbon::parse($orderDate);
            $todayOrderCount = Order::whereDate('order_date', $today)->count();

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

        $authUserId = Auth::guard('vendor')->id() ?? Auth::guard('vendor_employee')->id();

        $order->vehicle_id = $vehicle_id ?? null;
        $order->restaurant_id = $restaurant->id;
        $order->order_taken_by = $order->order_taken_by ?? $authUserId;
        $order->zone_id = $restaurant->zone_id;
        $order->session_id = $order->session_id ?? $activeSession->session_id;
        $order->delivery_charge = isset($address) ? $address['delivery_fee'] : 0;
        $order->delivery_charge += isset($cart['delivery_fee']) ? $cart['delivery_fee'] : 0;
        // $order->original_delivery_charge = isset($address) ? $address['delivery_fee'] : 0;
        $order->original_delivery_charge = 0;
        $order->delivery_address = isset($address) ? json_encode($address) : null;
        $order->checked = 1;
        $order->order_note = $request->order_notes ?? '';
        $order->updated_at = now();
        $order->otp = rand(1000, 9999);
        $order->partner_id = $request->partner_id ?? '';
        $order->is_pushed = 'N';

        // dd($request->all(), $request->partner_id);
        // Set the User ID
        if($order->payment_status == 'paid'){
            $order->payment_user_id = $authUserId;
            $order->payment_user_session_id = $activeSession->session_id;
        }

        if(isset($request->phone) && !empty($request->phone)){
            $customer = SaleCustomer::where('customer_mobile_no', $request->phone);
            if($customer->exists()){
                $customer = $customer->first();
                $customer->customer_name = $request->customer_name ?? $customer->customer_name;
                $customer->customer_email = $request->customer_email ?? $customer->customer_email;
                $order->user_id = $customer->customer_id;
                $customer->is_pushed = 'N';
                $customer->save();
            }else{
                // Create a new customer
                $customer = SaleCustomer::create([
                    'customer_code' => SaleCustomer::generateCustomerCode(),
                    'customer_type' => '19148225011030',
                    'customer_name' => $request->customer_name ?? 'Customer - ' . $request->phone,
                    'customer_mobile_no' => $request->phone,
                    'customer_email' => $request->customer_email ?? null,
                    'customer_user_id' => $authUserId,
                    'business_id' => 1,
                    'company_id' => 1,
                    'branch_id' => $branchId,
                    'customer_id' => SaleCustomer::generateCustomerId($branchId)
                ]);

                $order->user_id = $customer->customer_id;
            }
        }else{
            $order->user_id = $request->user_id;
        }

        DB::beginTransaction();

        foreach ($cart as $c) {
            if (is_array($c)) {

                 if($c['discount'] > ($c['price'] * $c['quantity'])){
                    Toastr::error(translate('messages.discount_cannot_exceed_product_amount'));
                    return back()->withInput();
                }

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

                    $variation_data = Helpers::get_varient($product->variations, $c['variations'], $request->partner_id);
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
                        'is_deleted' => isset($c['is_deleted']) ? trim($c['is_deleted']) : 'N',
                        'cancel_reason'      => isset($c['cancel_reason']) ? trim($c['cancel_reason']) : '',
                        'cooking_status'     => isset($c['cooking_status']) ? trim($c['cooking_status']) : '',
                        'cancel_text'        => isset($c['cancel_text']) ? trim($c['cancel_text']) : '',
                        'is_printed' => $c['is_printed'] ?? 0,
                        'options_changed' => $c['options_changed'] ?? 0,
                        'payment_status' => $c['payment_status'] ?? 'unpaid',
                        'food_create_time'  => $c['food_create_time'] ?? Carbon::now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    error_log('Cart variations structure: ' . json_encode($c['variations']));
                    error_log('Cart variations keys: ' . json_encode(array_keys($c['variations'] ?? [])));
                    if (isset($c['variations'][0]['values'])) {
                        error_log('First variation values: ' . json_encode($c['variations'][0]['values']));
                    }
                    error_log('Order detail variation field: ' . $or_d['variation']);

                    $order_details[] = $or_d;

                    if($or_d['is_deleted'] == 'Y'){
                        continue;
                    }else{
                        $total_addon_price += $or_d['total_add_on_price'];
                        $product_price += $price * $or_d['quantity'];
                        $discount_on_product += $or_d['discount_on_food'] * $or_d['quantity'];
                    }
                }
            }
        }

        $order->discount_on_product_by = 'vendor';
        $restaurant_discount = Helpers::get_restaurant_discount($restaurant);
        if (isset($restaurant_discount)) {
            $order->discount_on_product_by = 'admin';
        }
        if (isset($cart['discount'])) {
            $restaurant_discount_amount += $cart['discount_type'] == 'percent' && $cart['discount'] > 0 ? ((($product_price + $total_addon_price - $discount_on_product) * $cart['discount']) / 100) : $cart['discount'];
        }

        $total_price = $product_price + $total_addon_price - $discount_on_product - $restaurant_discount_amount;
        $tax = isset($cart['tax']) ? $cart['tax'] : $restaurant->tax;

        if($total_price < 0){
            Toastr::error(translate('messages.total_price_cannot_be_negative'));
            return back()->withInput();
        }

        // Allow 100% discount orders even if total = 0
        if ($total_price > 0 && $request->order_draft == 'final') {
            if (floatval($request->cash_paid) < 0 || floatval($request->card_paid) < 0) {
                Toastr::error(translate('Payment amount cannot be negative'));
                return back();
            }

            if (!(isset($request->select_payment_type) && $request->select_payment_type=='credit_payment' ) && floatval($request->cash_paid) == 0 && floatval($request->card_paid) == 0) {
                Toastr::error(translate('Payment amount cannot be zero for paid orders'));
                return back();
            }
        }

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

            // if ($request->order_draft == 'final') {

            //     $cash = floatval($request->cash_paid ?? 0);
            //     $card = floatval($request->card_paid ?? 0);
            //     $paidTotal = $cash + $card;

            //     if ($paidTotal < floatval($order->order_amount)) {
            //         Toastr::error(translate('messages.amount_cannot_exceed_total_order_amount'));
            //         return back()->withInput();
            //     }
            // }
            $order->save();

            if (!$editing_order_id) {
                KitchenOrderStatusLog::create([
                    "status" => 'pending',
                    "order_id" => $order->id,
                    "id" => $order->id . '1',
                ]);
            } 

            $dirttyOrderDetails = [];
            if ($editing_order_id) {
                $dirtyOrder = OrderDetail::where('order_id', $order->id);
                $dirttyOrderDetails = $dirtyOrder->get()->toArray();
                $dirtyOrder->delete();
            }

            // Prepare new order details
            foreach ($order_details as $key => $item) {
                $order_details[$key]['id'] = $order->id . $key;
                $order_details[$key]['order_id'] = $order->id;
            }

            // Insert new order details
            OrderDetail::insert($order_details);

            // Fetch new details after insert
            $newOrderDetails = OrderDetail::where('order_id', $order->id)->get()->toArray();

            // Compare old and new
            if ($editing_order_id && $dirttyOrderDetails !== $newOrderDetails) {
                // Save Log for edited order only if different
                // Helpers::create_all_logs($order, 'order_edited', 'Order');
                // Helpers::create_all_logs($order, 'order_detail_edited', 'OrderDetail', $dirttyOrderDetails, $newOrderDetails);
            }



            $posOrderDtl = PosOrderAdditionalDtl::firstOrNew(['order_id' => $order->id]);
            $posOrderDtl->id = $order->id . '1';
            $posOrderDtl->restaurant_id = $order->restaurant_id;
            $posOrderDtl->customer_name = $request->customer_name;
            $posOrderDtl->car_number = $request->car_number;
            
            $posOrderDtl->phone = $request->phone;
            $posOrderDtl->invoice_amount = $order->order_amount ?? 0;
            // dd($order->order_amount,$request->invoice_amount);
            if(isset($request->select_payment_type) && $request->select_payment_type=='credit_payment'){
                $posOrderDtl->credit_paid = $order->order_amount ?? 0;
            }else{
                $posOrderDtl->cash_paid = $request->cash_paid ?? 0;
                $posOrderDtl->card_paid = $request->card_paid ?? 0;
            }

            if($request->select_payment_type && ($request->select_payment_type=='card_payment' || $request->select_payment_type=='both_payment')){
                session(['bank_account' => $request->bank_account]);
            }

            if($payment_type == 'card' || $payment_type == 'cash_card'){
                $posOrderDtl->bank_account = $request->bank_account;
            }else{
                $posOrderDtl->bank_account = null;
            }
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
                event(new myevent('unpaid'));
                $printController = new \App\Http\Controllers\PrintController();

                if($order->payment_status == 'unpaid' && $order->printed == 0){
                    $printController->printOrderKitchen(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
                }
                
                if($order->payment_status == 'unpaid' && $order->printed == 1){
                    // That means we are in editing case
                    $requirePrint = false;
                    foreach ($order->details as $detail) {
                        if ($detail->options_changed == 1 || $detail->is_printed == 0 || $detail->is_deleted == 'Y') {
                            $requirePrint = true;
                            break;
                        }
                    }
                    
                    if($requirePrint){
                        $printController->printOrderKitchen(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
                    }
                }

                if($order->payment_status == 'paid'){
                    if($order->printed == 0){
                        $printController->printOrderKitchen(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
                    }
                    $printController->printOrder(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
                }

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

            if(isset($request->partner_id) && !empty($request->partner_id)){
                return redirect()->to('restaurant-panel/pos/new');
            }else{
                return redirect()->back();
            }

            // return back();
        } catch (\Exception $exception) {
            DB::rollBack();
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }

        Toastr::warning(translate('messages.failed_to_place_order'));
        return back();
    }

    public function loadDraftOrderToCart($order_id)
    {
        // dd('fsd');
        $order = Order::with('details')->find($order_id);

        // if (!$order || $order->payment_status != 'unpaid') {
        //     Toastr::error('Only unpaid (draft) orders can be edited.');
        //     return back();
        // }
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
                'is_deleted' => trim($item->is_deleted),
                'is_printed' => $item->is_printed,
                'options_changed' => 0,
                'cancel_reason'      => trim($item->cancel_reason),
                'cooking_status'     => trim($item->cooking_status),
                'cancel_text'        => trim($item->cancel_text),
                'payment_status'   => $item->payment_status ?? 'unpaid',
                'food_create_time' => $item->food_create_time ?? null,
                'image_full_url' => $food['image_full_url'] ?? null,
                'maximum_cart_quantity' => $food['maximum_cart_quantity'] ?? 1000,
                'draft_product' => true,
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
        return redirect()->route('vendor.pos.index.new', ['id' => $order->partner_id]);
    }


    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'nullable',
            'phone' => 'required',
            'email' => 'nullable|email',
        ]);
        $authUserId = Auth::guard('vendor')->id() ?? Auth::guard('vendor_employee')->id();
        $branchId = Helpers::get_restaurant_id();
        $customerName = $request['f_name'] ?? 'Customer - ' . $request['phone'];

        $customer = SaleCustomer::where('customer_mobile_no', $request['phone'])
            ->where('branch_id', $branchId)
            ->first();
        
        if($customer){
            if($request->ajax()){
                return response()->json(['message' => translate('messages.customer_with_this_phone_number_already_exists')], 409);
            }else{
                Toastr::error(translate('messages.customer_with_this_phone_number_already_exists'));
                return back();
            }
        }

        SaleCustomer::create([
            'customer_code' => SaleCustomer::generateCustomerCode(),
            'customer_type' => '19148225011030',
            'customer_name' => $customerName,
            'customer_mobile_no' => $request['phone'],
            'customer_email' => $request['email'],
            'customer_user_id' => $authUserId,
            'business_id' => 1,
            'company_id' => 1,
            'branch_id' => $branchId,
            'customer_id' => SaleCustomer::generateCustomerId($branchId)
        ]);

        if(!$request->ajax()){
            Toastr::success(translate('customer_added_successfully'));
            return back();
        }else{
            return response()->json(translate('customer_added_successfully'), 200);
        }
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
    private function isLiveServerReachable()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(2) // Only 2 seconds
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying()
                ->get(config('services.live_server.url') . '/api/health-check');

            return $response->successful();
        } catch (\Exception $e) {
            info('Live server unreachable: ' . $e->getMessage());
            return false;
        }
    }
}
