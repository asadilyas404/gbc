<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Order;
use App\Models\DeliveryMan;
use App\Exports\OrderExport;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use Illuminate\Support\Facades\Log;
use App\Exports\OrderRefundExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SyncOrdersJob;
use Illuminate\Support\Str;
use App\Events\myevent;
use App\Http\Controllers\PrintController;
use Carbon\Carbon;
use Mpdf\Mpdf;
class OrderController extends Controller
{
    public function list($status , Request $request)
    {
        // dd('dsa',$status ,  $request->all());
        $key = explode(' ', $request['search']);

        $data =0;
        $restaurant =Helpers::get_restaurant_data();
        if (($restaurant->restaurant_model == 'subscription' &&  $restaurant->restaurant_sub->self_delivery == 1)  || ($restaurant->restaurant_model == 'commission' &&  $restaurant->self_delivery_system == 1) ){
            $data =1;
        }

        Order::where(['checked' => 0])->where('restaurant_id',Helpers::get_restaurant_id())->update(['checked' => 1]);

        $orders = Order::with(['customer', 'pos_details'])
        ->when($status == 'searching_for_deliverymen', function($query){
            return $query->SearchingForDeliveryman();
        })
        ->when($status == 'confirmed', function($query){
            return $query->whereIn('order_status',['confirmed'])->whereNotNull('confirmed');
        })
        ->when($status == 'pending', function($query) use($data){
            if(config('order_confirmation_model') == 'restaurant' || $data)
            {
                return $query->where('order_status','pending');
            }
            else
            {
                return $query->where('order_status','pending')->where('order_type', 'take_away');
            }
        })
        ->when($status == 'cooking', function($query){
            return $query->where('order_status','processing');
        })
        ->when($status == 'accepted', function($query){
            return $query->where('order_status','accepted');
        })
        ->when($status == 'food_on_the_way', function($query){
            return $query->where('order_status','picked_up');
        })
        ->when($status == 'delivered', function($query){
            return $query->Delivered();
        })
        ->when($status == 'ready_for_delivery', function($query){
            return $query->where('order_status','handover');
        })
        ->when($status == 'refund_requested', function($query){
            return $query->Refund_requested();
        })
        ->when($status == 'refunded', function($query){
            return $query->Refunded();
        })
        ->when($status == 'payment_failed', function($query){
            return $query->where('order_status','failed');
        })
        ->when($status == 'canceled', function($query){
            return $query->where('order_status','canceled');
        })
         ->when($status == 'assinged', function($query){
             return $query->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded','delivered','refund_request_canceled'])->whereNotNull('delivery_man_id');
         })

        ->when($status == 'scheduled', function($query) use($data){
            return $query->Scheduled()->where(function($q) use($data){
                if(config('order_confirmation_model') == 'restaurant' || $data)
                {
                    $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                }
                else
                {
                    $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                        $query->where('order_status','pending')->where('order_type', 'take_away');
                    });
                }
            });
        })
        ->when($status == 'all', function($query) use($data){
            return $query->where(function($q1) use($data) { //->where('updated_at', '>=', Carbon::now()->subHours(1))
                $q1->whereNotIn('order_status',(config('order_confirmation_model') == 'restaurant'|| $data)?['failed', 'refund_requested', 'refunded']:['pending','failed', 'refund_requested', 'refunded'])
                ->orWhere(function($q2){
                    return $q2->where('order_status','pending')->where('order_type', 'take_away');
                })->orWhere(function($q3){
                    return $q3->where('order_status','pending')->whereNotNull('subscription_id');
                });
            });
        })
        ->when($status == 'draft', function($query){
           return $query->where('payment_status', 'unpaid')
             ->where('order_status', '!=', 'canceled');
        })
        ->when(in_array($status, ['pending','confirmed']), function($query){
            return $query->OrderScheduledIn(30);
        })
        ->when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
        })
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('tbl_soft_branch')
                ->whereColumn('tbl_soft_branch.branch_id', 'orders.restaurant_id')
                ->whereColumn('tbl_soft_branch.orders_date', 'orders.order_date');
        })
        ->Notpos()
        // ->NotDigitalOrder()
        ->hasSubscriptionToday()
        ->where('restaurant_id',\App\CentralLogics\Helpers::get_restaurant_id())
        ->orderBy('schedule_at', 'desc')
        ->paginate(config('default_pagination'));

        // Calculate order statistics
        $totalOrders = $orders->total();
        $paidOrders = $orders->where('payment_status', 'paid')
        ->where('order_status', '!=', 'canceled')
        ->count();
        $unpaidOrders = $orders->where('payment_status', 'unpaid')
        ->where('order_status', '!=', 'canceled')
        ->count();
        $canceledOrders = $orders->where('order_status', 'canceled')->count();

        // Calculate amounts
        $paidAmount = $orders->where('payment_status', 'paid')
        ->sum('order_amount');
        $canceledAmount = $orders
        ->where('order_status', 'canceled')
        ->whereIn('payment_status', ['paid'])
        ->sum('order_amount');
        $unpaidAmount = $orders->where('payment_status', 'unpaid')
        ->where('order_status', 'pending')
        ->sum('order_amount');

        foreach($orders as $o){
            $o->partner_name = DB::table('tbl_sale_order_partners')->where('partner_id',$o->partner_id)->value('partner_name');
        }

        $totalAmount = $paidAmount - $canceledAmount;

        $st=$status;
        $status = translate('messages.'.$status);
        return view('vendor-views.order.list', compact('orders', 'status','st', 'totalOrders', 'paidOrders', 'unpaidOrders','canceledOrders', 'totalAmount', 'paidAmount', 'unpaidAmount','canceledAmount'));
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $orders=Order::where(['restaurant_id'=>Helpers::get_restaurant_id()])->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->Notpos()
        ->NotDigitalOrder()
        ->limit(100)->get();
        return response()->json([
            'view'=>view('vendor-views.order.partials._table',compact('orders'))->render()
        ]);
    }

    public function details(Request $request,$id)
    {
        $order = Order::with(['offline_payments','payments','subscription','subscription.schedule_today','details', 'customer'=>function($query){
            return $query->withCount('orders');
        },'delivery_man'=>function($query){
            return $query->withCount('orders');
        }])->where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])

        ->Notpos()
        // ->NotDigitalOrder()
        // ->hasSubscriptionToday()
        ->first();

        if (isset($order)) {
        $deliveryMen = DeliveryMan::with('last_location')->where('restaurant_id',Helpers::get_restaurant_id())->active()->get();
        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen, $order->restaurant->latitude,$order->restaurant->longitude);

        $selected_delivery_man = DeliveryMan::with('last_location')->where('id',$order->delivery_man_id)->first() ?? [];
        if($order->delivery_man){
            $selected_delivery_man = Helpers::deliverymen_list_formatting($selected_delivery_man, $order->restaurant->latitude,  $order->restaurant->longitude , true);
        }
            return view('vendor-views.order.order-view', compact('order', 'selected_delivery_man' , 'deliveryMen'));
        } else {
            Toastr::info('No more orders!');
            return back();
        }
    }

    public function quickView($id, $p_id=null) //$p_id
    {
        $order = Order::with('details')->findOrFail($id);

        $html = '';

        foreach ($order->details as $detail) {
            if (isset($detail->food_id)) {
                $foodData = json_decode($detail->food_details, true);
                $food = \App\Models\Food::find($foodData['id']);
                $image = $food->image_full_url ?? asset('public/assets/admin/img/100x100/food-default-image.png');
                $name = Str::limit($foodData['name'], 25, '...');
                $nameAr = Str::limit($food->getTranslationValue('name', 'ar'), 25, '...');
                $discoPrice = $detail['price'] - $detail['discount_on_food'];
                $price = \App\CentralLogics\Helpers::format_currency($discoPrice) ;
                $qty = $detail['quantity'];

                $html .= '<tr ' . ($detail->is_deleted == "Y" ? "class='bg-danger'" : "") . '>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="' . $image . '" alt="food" class="rounded" style="width:50px;height:50px;object-fit:cover;margin-right:10px;">
                            <div>
                                <div><strong>' . $name . '</strong></div>
                                <div><small>' . $nameAr . '</small></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">' . $qty . '</td>
                    <td>' . $price . '</td>
                </tr>';

            }
        }

        return $html ?: '<tr><td colspan="3" class="text-center">No items found.</td></tr>';
    }


    public function getPaymentData($id)
{
    try {
        // Load order with relationships
        $order = Order::with('details', 'pos_details')->findOrFail($id);

        if (!$order->pos_details) {
            return response()->json(['error' => 'POS details not found for this order.'], 404);
        }

        $cart = [];

        foreach ($order->details as $item) {
            $food = json_decode($item->food_details, true) ?? [];

            // Safe defaults
            $variation_price = 0;
            $variations = json_decode($item->variation, true) ?? [];

            foreach ($variations as $variation) {
                $variation_price += $variation['optionPrice'] ?? 0;
            }

            // Simplify variations (wrap in try-catch if needed)
            $simplified_variations = method_exists(Helpers::class, 'simplifyVariationsToLabels')
                ? Helpers::simplifyVariationsToLabels($variations)
                : [];
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
                'is_deleted'=> $item->is_deleted ?? 'N',
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

        // Toastr::success('Unpaid order loaded to cart.');

        // Return payment data
        return response()->json([
            'total_amount_formatted' => Helpers::format_currency($order->pos_details->invoice_amount ?? 0),
            'customer_name' => $order->pos_details->customer_name ?? '',
            'car_number' => $order->pos_details->car_number ?? '',
            'phone' => $order->pos_details->phone ?? '',
            'cash_paid' => $order->pos_details->cash_paid ?? 0,
            'card_paid' => $order->pos_details->card_paid ?? 0,
            'delivery_type' => $order->order_type ?? '',
            'bank_account' => $order->pos_details->bank_account ?? session('bank_account'),
            'partner_id'    => $order->partner_id ?? ''
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['error' => 'Order not found.'], 404);
    } catch (\Throwable $e) {
        Log::error('Error in getPaymentData: ' . $e->getMessage(), [
            'order_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Something went wrong while loading payment data.'], 500);
    }
}


    public function status(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'order_status' => 'required|in:confirmed,processing,handover,delivered,canceled',
            'reason' =>'required_if:order_status,canceled',
        ],[
            'id.required' => 'Order id is required!'
        ]);

        $order = Order::where(['id' => $request->id, 'restaurant_id' => Helpers::get_restaurant_id()])->with(['subscription_logs','details'])->first();

        // if($order->delivered != null)
        // {
        //     Toastr::warning(translate('messages.cannot_change_status_after_delivered'));
        //     return back();
        // }

        // if($request['order_status']=='canceled' && !config('canceled_by_restaurant'))
        // {
        //     Toastr::warning(translate('messages.you_can_not_cancel_a_order'));
        //     return back();
        // }

        // if($request['order_status']=='canceled' && $order->confirmed)
        // {
        //     Toastr::warning(translate('messages.you_can_not_cancel_after_confirm'));
        //     return back();
        // }

        $data =0;
        $restaurant =Helpers::get_restaurant_data();
        if (($restaurant->restaurant_model == 'subscription' && $restaurant->restaurant_sub->self_delivery == 1)  || ($restaurant->restaurant_model == 'commission' &&  $restaurant->self_delivery_system == 1) ){
        $data =1;
        }

        // if($request['order_status']=='delivered' && $order->order_type != 'take_away' && !$data)
        // {
        //     Toastr::warning(translate('messages.you_can_not_delivered_delivery_order'));
        //     return back();
        // }

        if($request['order_status'] =="confirmed")
        {
            if(!$data && config('order_confirmation_model') == 'deliveryman' && $order->order_type != 'take_away' && $order->subscription_id == null )
            {
                Toastr::warning(translate('messages.order_confirmation_warning'));
                return back();
            }
        }

        // if ($request->order_status == 'delivered') {
        //     $order_delivery_verification = (boolean)\App\Models\BusinessSetting::where(['key' => 'order_delivery_verification'])->first()->value;
        //     if($order_delivery_verification)
        //     {
        //         if($request->otp)
        //         {
        //             if($request->otp != $order->otp)
        //             {
        //                 Toastr::warning(translate('messages.order_varification_code_not_matched'));
        //                 return back();
        //             }
        //         }
        //         else
        //         {
        //             Toastr::warning(translate('messages.order_varification_code_is_required'));
        //             return back();
        //         }
        //     }
        //     if(isset($order->subscription_id) && count($order->subscription_logs) == 0 ){
        //         Toastr::warning(translate('messages.You_Can_Not_Delivered_This_Subscription_order_Before_Schedule'));
        //         return back();
        //     }

        //     if($order->transaction  == null || isset($order->subscription_id))
        //     {
        //         $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first()->payment_method;
        //         $unpaid_pay_method = 'digital_payment';
        //         if($unpaid_payment){
        //             $unpaid_pay_method = $unpaid_payment;
        //         }

        //         if($order->payment_method == 'cash_on_delivery' || $unpaid_pay_method == 'cash_on_delivery')
        //         {
        //             $ol = OrderLogic::create_transaction($order,'restaurant',  null);
        //         }
        //         else{
        //             $ol = OrderLogic::create_transaction($order,'admin',  null);
        //         }


        //         if(!$ol)
        //         {
        //             Toastr::warning(translate('messages.faield_to_create_order_transaction'));
        //             return back();
        //         }
        //     }

        //     $order->payment_status = 'paid';

        //     OrderLogic::update_unpaid_order_payment($order->id, $order->payment_method);

        //     $order->details->each(function($item, $key){
        //         if($item->food)
        //         {
        //             $item->food->increment('order_count');
        //         }
        //     });
        //     $order->customer ?  $order->customer->increment('order_count') : '';
        // }
        // if($request->order_status == 'canceled' || $request->order_status == 'delivered')
        // {
        //     if($order->delivery_man)
        //     {
        //         $dm = $order->delivery_man;
        //         $dm->current_orders = $dm->current_orders>1?$dm->current_orders-1:0;
        //         $dm->save();
        //     }
        // }

        if($request->order_status == 'canceled' )
        {
            Helpers::increment_order_count($order->restaurant);
            $order->cancellation_reason = $request->reason;
            $order->canceled_by = 'restaurant';
            if(!isset($order->confirmed) && isset($order->subscription_id)){
                $order->subscription()->update(['status' => 'canceled']);
                    if($order->subscription->log){
                        $order->subscription->log()->update([
                            'order_status' => $request->status,
                            'canceled' => now(),
                            ]);
                    }
            }
            Helpers::decreaseSellCount($order->details);
        }
        // if($request->order_status == 'delivered')
        // {
        //     $order->restaurant->increment('order_count');
        //     if($order->delivery_man)
        //     {
        //         $order->delivery_man->increment('order_count');
        //     }
        // }
        $order->order_status = $request->order_status;
        if ($request->order_status == "processing") {
            $order->processing_time = $request->processing_time;
        }
        $order[$request['order_status']] = now();
        $order->save();

        // Send Order Canceled Print Command to Kitchen Printer
        if($order->order_status == 'canceled'){
            try {
                $printController = new \App\Http\Controllers\PrintController();
                $printController->printOrderKitchen(new \Illuminate\Http\Request(['order_id' => (string)  $order->id]));
            } catch (\Exception $printException) {
                info('Print error On Order Cancel: ' . $printException->getMessage());
            }
        }


        // if(!Helpers::send_order_notification($order))
        // {
        //     Toastr::warning(translate('messages.push_notification_faild'));
        // }
        OrderLogic::update_subscription_log($order);
        Toastr::success(translate('messages.order_status_updated'));
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success('Delivery address updated!');
        return back();
    }

    public function generate_invoice($id)
    {
        $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->with(['payments'])->first();
        return view('vendor-views.order.invoice', compact('order'));
    }

    public function generate_order_receipt($id)
    {
        $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->with(['payments', 'details.food'])->first();
        $maxMakeTime = $order->details
        ->map(function($detail) { return $detail->food->est_make_time ?? 0; })
        ->max();
        return view('vendor-views.order.receipt', compact('order', 'maxMakeTime'));
    }

    public function print_order($id)
    {
        try {
            $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])
                ->with(['payments', 'details.food', 'restaurant'])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Generate bill content using the new_invoice template
            $billContent = view('new_invoice', compact('order'))->render();

            // Generate kitchen content using the kitchen_receipt template
            $kitchenContent = view('kitchen_receipt', compact('order'))->render();

            return response()->json([
                'success' => true,
                'bill_content' => $billContent,
                'kitchen_content' => $kitchenContent
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate print content: ' . $e->getMessage()
            ], 500);
        }
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
    }

    public function fetchCustomerPhone(Request $request, $id)
    {
        $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])
            ->with(['customer', 'pos_details'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $phone = null;
        if ($order->customer && $order->customer->phone) {
            $phone = $order->customer->phone;
        } elseif ($order->pos_details && $order->pos_details->phone) {
            $phone = $order->pos_details->phone;
        }
        if (!$phone) {
            return response()->json(['error' => 'Customer phone number not found'], 404);
        }

        return response()->json(['phone' => $phone]);
    }

    public function generatePdfForWhatsApp(Request $request, $id)
    {
        $order = Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])
            ->with(['payments', 'details.food', 'restaurant', 'customer'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        $fullView = view('new_invoice', compact('order'))->render();

        $printableContent = '';

        if (class_exists('DOMDocument')) {
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $fullView, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $xpath = new \DOMXPath($dom);
            $printableArea = $xpath->query('//*[@id="printableArea"]')->item(0);

            if ($printableArea) {
                $printableContent = '';
                foreach ($printableArea->childNodes as $child) {
                    $printableContent .= $dom->saveHTML($child);
                }

                $nonPrintables = $xpath->query('.//*[contains(@class, "non-printable")]', $printableArea);
                foreach ($nonPrintables as $node) {
                    $node->parentNode->removeChild($node);
                }

                $printableContent = '';
                foreach ($printableArea->childNodes as $child) {
                    $printableContent .= $dom->saveHTML($child);
                }
            }
            libxml_clear_errors();
        }

        if (empty($printableContent)) {
            if (preg_match('/<div[^>]*id=["\']printableArea["\'][^>]*>([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>/i', $fullView, $matches)) {
                $printableContent = $matches[1];
            } else {
                $printableContent = $fullView;
            }

            $printableContent = preg_replace('/<[^>]*class="[^"]*non-printable[^"]*"[^>]*>[\s\S]*?<\/[^>]+>/i', '', $printableContent);
            $printableContent = preg_replace('/<[^>]*class="[^"]*non-printable[^"]*"[^>]*\/?>/i', '', $printableContent);
        }

        $printableContent = preg_replace_callback(
            '/src=["\']([^"\']+)["\']/i',
            function($matches) {
                $src = $matches[1];
                if (strpos($src, '/public/assets/') !== false || strpos($src, 'assets/') !== false) {

                    $path = str_replace('/public/', '', $src);
                    $path = str_replace('public/', '', $path);
                    $absolutePath = public_path($path);
                    if (file_exists($absolutePath)) {
                        return 'src="' . $absolutePath . '"';
                    }
                }
                if (strpos($src, 'http') === 0 || strpos($src, '/') === 0) {
                    return $matches[0];
                }
                $absolutePath = public_path($src);
                if (file_exists($absolutePath)) {
                    return 'src="' . $absolutePath . '"';
                }
                return $matches[0];
            },
            $printableContent
        );

        $css = '<style>
            * { box-sizing: border-box; }
            body { margin: 0; padding: 20px; font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif; font-size: 14px; line-height: 1.5; }

            /* Bootstrap utilities for centering */
            .row { display: flex; flex-wrap: wrap; margin-left: -15px; margin-right: -15px; }
            .row::before, .row::after { content: ""; display: table; }
            .row::after { clear: both; }
            .col-md-12 { position: relative; width: 100%; padding-left: 15px; padding-right: 15px; }
            .col-12 { width: 100%; padding: 0 15px; }
            .col-6 { width: 50%; padding: 0 15px; }
            .justify-content-center { justify-content: center !important; }
            .justify-content-between { justify-content: space-between !important; }
            .justify-content-end { justify-content: flex-end !important; }
            .text-center { text-align: center !important; }
            .text-right { text-align: right !important; }
            .text-left { text-align: left !important; }
            .text-end { text-align: right !important; }
            .container-fluid { width: 100%; padding-left: 15px; padding-right: 15px; margin-left: auto; margin-right: auto; }

            /* Flexbox utilities - mPDF compatible using table display */
            .d-flex { display: table !important; width: 100%; }
            .d-flex > * { display: table-cell !important; vertical-align: middle; }
            .d-block { display: block !important; }
            .d-inline-block { display: inline-block !important; }
            .flex-row { display: table !important; width: 100%; }
            .flex-row > * { display: table-cell !important; vertical-align: middle; }
            .flex-column { display: block !important; }
            .flex-wrap { display: block !important; }
            .align-items-center > * { vertical-align: middle !important; }
            .align-items-start > * { vertical-align: top !important; }
            .align-items-end > * { vertical-align: bottom !important; }
            .justify-content-between { width: 100%; }
            .justify-content-between > *:first-child { text-align: left !important; }
            .justify-content-between > *:last-child { text-align: right !important; }
            .gap-1 > * { padding-right: 0.25rem !important; }
            .gap-1 > *:last-child { padding-right: 0 !important; }
            .gap-2 > * { padding-right: 0.5rem !important; }
            .gap-2 > *:last-child { padding-right: 0 !important; }
            .gap-3 > * { padding-right: 1rem !important; }
            .gap-3 > *:last-child { padding-right: 0 !important; }

            /* Invoice specific styles for centering */
            .initial-38-1 {
                max-width: 382px;
                margin: 0 auto;
                padding-inline-end: 4px;
            }
            .initial-38-1 * {
                font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif !important;
                font-weight: 500;
                color: #000000;
            }
            /* Logo centering - ensure logo is properly centered and not stretched */
            .initial-38-1 .pt-3:first-child {
                padding-top: 0 !important;
                text-align: center !important;
                display: block;
                width: 100%;
            }
            .initial-38-1 .pt-3:first-child img,
            .initial-38-1 img.initial-38-2,
            img.initial-38-2 {
                margin: 0 auto !important;
                display: block !important;
                height: 70px !important;
                width: auto !important;
                max-width: 100% !important;
                object-fit: contain !important;
                object-position: center !important;
            }
            .initial-38-2 {
                height: 70px !important;
                width: auto !important;
                max-width: 100% !important;
                object-fit: contain !important;
                display: block !important;
                margin: 0 auto !important;
            }
            .initial-38-3 {
                line-height: 1;
            }
            .initial-38-4 {
                font-size: 16px;
                font-weight: lighter;
            }
            .initial-38-9 {
                width: 98%;
                margin-inline-start: auto;
                margin-inline-end: auto;
            }
            .initial-38 #printableArea * { color: #000000; }
            #printableArea { margin: 0 !important; }
            #printableArea > .col-md-12 { padding: 0 !important; }
            .content { max-width: 100%; }

            /* Table styles for proper alignment */
            table { width: 100%; border-collapse: collapse; margin: 0; }
            .table { width: 100%; margin-bottom: 1rem; }
            .table-borderless { border: none; }
            .table-borderless th, .table-borderless td { border: none; padding: 0.5rem; }
            .table-bordered { border: 1px solid #dee2e6; }
            .table-bordered th, .table-bordered td { border: 1px solid #dee2e6; padding: 0.75rem; }
            .table-bordered th:first-child, .table-bordered td:first-child { padding-inline-start: 0 !important; }
            .table-bordered th:last-child, .table-bordered td:last-child { text-align: end; padding-inline-end: 10px; }
            .table-align-middle th, .table-align-middle td { vertical-align: middle; }
            th, td { padding: 0.5rem; vertical-align: top; }
            thead th { font-weight: bold; }
            .w-28p { width: 28%; }

            /* Description list (dl) styles for totals section - mPDF compatible */
            dl.row { display: table; width: 100%; margin: 0; }
            dl.row dt, dl.row dd { display: inline-block; width: 49%; vertical-align: top; }
            dl.row dt { padding: 0.3rem 0.5rem; font-weight: normal; text-align: left; }
            dl.row dd { padding: 0.3rem 0.5rem; margin: 0; text-align: right; }
            dl.row dt.col-6, dl.row dd.col-6 { width: 49%; }
            dl.row dd.col-12 { width: 100%; display: block; }

            /* Spacing utilities */
            .pt-3 { padding-top: 1rem !important; }
            .pt-1 { padding-top: 0.25rem !important; }
            .pb-1 { padding-bottom: 0.25rem !important; }
            .pb-2 { padding-bottom: 0.5rem !important; }
            .pb-3 { padding-bottom: 1rem !important; }
            .p-3 { padding: 1rem !important; }
            .px-3 { padding-left: 1rem !important; padding-right: 1rem !important; }
            .py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
            .mt-1 { margin-top: 0.25rem !important; }
            .mt-2 { margin-top: 0.5rem !important; }
            .mb-0 { margin-bottom: 0 !important; }
            .mb-1 { margin-bottom: 0.25rem !important; }
            .mb-2 { margin-bottom: 0.5rem !important; }
            .mb-3 { margin-bottom: 1rem !important; }
            .my-2 { margin-top: 0.5rem !important; margin-bottom: 0.5rem !important; }
            .ml-3 { margin-left: 1rem !important; }
            .mr-2 { margin-right: 0.5rem !important; }

            /* Text utilities */
            .text-break { word-wrap: break-word !important; word-break: break-word !important; }
            .text-nowrap { white-space: nowrap !important; }
            .text-capitalize { text-transform: capitalize !important; }
            .text-uppercase { text-transform: uppercase !important; }
            .text-muted { color: #6c757d !important; }
            .text-body { color: #212529 !important; }
            .fw-bold { font-weight: bold !important; }
            .font-weight-bold { font-weight: bold !important; }
            .fw-500 { font-weight: 500 !important; }
            .font-light { font-weight: 300 !important; }
            .fz-12px { font-size: 12px !important; }
            .fz-20px { font-size: 20px !important; }
            .font-size-sm { font-size: 0.875rem !important; }

            /* Border utilities */
            .border { border: 1px solid #dee2e6 !important; }
            .border-dashed { border-style: dashed !important; }
            .border-secondary { border-color: #6c757d !important; }
            .border-bottom-dashed { border-bottom: 1px dashed #979797 !important; }
            .rounded { border-radius: 0.25rem !important; }

            /* Headings */
            h5 { margin: 0.5rem 0; font-size: 1rem; font-weight: 500; line-height: 1.4; }
            h5.d-flex { display: table !important; width: 100%; }
            h5.d-flex > span { display: table-cell !important; }
            h5.d-flex.justify-content-between > span:first-child { text-align: left !important; width: 50%; }
            h5.d-flex.justify-content-between > span:last-child { text-align: right !important; width: 50%; }
            h5.d-flex.gap-2 > span { padding-right: 0.5rem; }
            h5.d-flex.gap-2 > span:last-child { padding-right: 0; }

            /* Images */
            img { max-width: 100%; height: auto; display: block; }

            /* Ensure proper alignment for all content */
            .initial-38-1 {
                text-align: left;
                display: block;
                width: 100%;
            }
            .initial-38-1 .text-center { text-align: center !important; }

            /* Fix for content alignment issues */
            .initial-38-1 > div {
                width: 100%;
            }
            .initial-38-1 h5 {
                text-align: inherit;
            }
            .initial-38-1 h5.text-center {
                text-align: center !important;
            }

            /* Order info box styling */
            .initial-38-1 .border.border-dashed {
                border: 1px dashed #6c757d !important;
                padding: 1rem;
                border-radius: 0.25rem;
                margin-bottom: 1rem;
            }

            /* Variation and addon display */
            .initial-38-1 .ml-3 {
                margin-left: 1rem !important;
            }
            .initial-38-1 .mt-1 {
                margin-top: 0.25rem !important;
            }

            /* Payment and total section */
            .initial-38-9 dl.row dt,
            .initial-38-9 dl.row dd {
                padding: 0.3rem 0.5rem;
            }

            /* Footer section */
            .initial-38-1 .text-center span.d-block {
                display: block !important;
            }

            /* Payment row styling - mPDF compatible */
            .initial-38-1 > div.d-flex.flex-row {
                display: table !important;
                width: 100%;
                margin-bottom: 0.25rem;
            }
            .initial-38-1 > div.d-flex.flex-row > span {
                display: table-cell !important;
            }
            .initial-38-1 > div.d-flex.flex-row.justify-content-between > span:first-child {
                text-align: left !important;
            }
            .initial-38-1 > div.d-flex.flex-row.justify-content-between > span:last-child {
                text-align: right !important;
            }

            /* Ensure span elements within d-flex are properly displayed */
            .d-flex span.text-capitalize {
                display: table-cell !important;
            }

            /* Make sure nested flex items display correctly */
            .d-flex > span > span {
                display: inline !important;
            }

            @page { margin: 10mm; }
        </style>';

        $view = '<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    ' . $css . '
</head>
<body>
    <div class="content container-fluid initial-38 new-invoice">
        <div class="row justify-content-center" id="printableArea">
            ' . $printableContent . '
        </div>
    </div>
</body>
</html>';

        $tempDir = storage_path('tmp');

        try {
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0777, true)) {
                    throw new \Exception("Failed to create temp directory: {$tempDir}");
                }
            }
            if (!is_writable($tempDir)) {
                if (!chmod($tempDir, 0777)) {
                    throw new \Exception("Temp directory is not writable: {$tempDir}");
                }
            }
            $mpdfSubDir = $tempDir . '/mpdf';
            if (!file_exists($mpdfSubDir)) {
                if (!mkdir($mpdfSubDir, 0777, true)) {
                    throw new \Exception("Failed to create mPDF subdirectory: {$mpdfSubDir}");
                }
            } else {
                if (!is_writable($mpdfSubDir)) {
                    if (!chmod($mpdfSubDir, 0777)) {
                        throw new \Exception("mPDF subdirectory is not writable: {$mpdfSubDir}");
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to setup PDF temp directory: ' . $e->getMessage() . '. Please ensure storage/tmp directory exists and is writable (chmod 777).'
            ], 500);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
            'default_font' => 'FreeSerif',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->WriteHTML($view);

        $filename = 'order_' . $order->order_serial . '_' . time() . '.pdf';
        $directory = 'orders';

        try {
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($directory)) {
                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($directory);
            }

            $filePath = storage_path('app/public/' . $directory . '/' . $filename);
            $mpdf->Output($filePath, 'F');

            $publicUrl = dynamicStorage('storage/app/public') . '/' . $directory . '/' . $filename;

            return response()->json([
                'success' => true,
                'url' => $publicUrl,
                'filePath' => $publicUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to save PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function sendWhatsappMsg(Request $request)
    {
        $to = $request->to;
        $message = $request->message;
        $filePath = $request->filePath;
        $orderId = $request->orderId;
        $orderSerial = $request->orderSerial;

        $apiUrl = config('whatsapp.intelligent.api_url');
        $appkey = config('whatsapp.intelligent.appkey');
        $authkey = config('whatsapp.intelligent.authkey');
        $sandbox = config('whatsapp.intelligent.sandbox');
        if (!$apiUrl || !$appkey || !$authkey) {
            return response()->json(['error' => 'WhatsApp API configuration is missing'], 500);
        }

        $curl = curl_init();

        if($filePath == '' || $filePath == null) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'appkey' => $appkey,
                    'authkey' => $authkey,
                    'to' => $to,
                    'message' => $message,
                    'sandbox' => $sandbox
                ),
            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'appkey' => $appkey,
                    'authkey' => $authkey,
                    'to' => $to,
                    'message' => $message,
                    'sandbox' => $sandbox,
                    'file' => $filePath
                ),
            ));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $responseData = @json_decode($response, true);

        if ($responseData) {
            if (isset($responseData['message_status']) && $responseData['message_status'] == 'Success') {
                return response()->json(['success' => 'Message sent successfully!']);
            } else {
                return response()->json(['error' => 'Message sending failed. API returned: ' . ($responseData['message_status'] ?? 'Unknown error')]);
            }
        } else {
            return response()->json(['error' => 'Invalid JSON response or empty response.', 'raw_response' => $response]);
        }
    }


    public function orders_export($status , Request $request)
    {
        try{
            $key = explode(' ', $request['search']);

            $data =0;
            $restaurant =Helpers::get_restaurant_data();
            if (($restaurant->restaurant_model == 'subscription' &&  $restaurant->restaurant_sub->self_delivery == 1)  || ($restaurant->restaurant_model == 'commission' &&  $restaurant->self_delivery_system == 1) ){
            $data =1;
            }

            Order::where(['checked' => 0])->where('restaurant_id',Helpers::get_restaurant_id())->update(['checked' => 1]);

            $orders = Order::with(['customer'])
            ->when($status == 'searching_for_deliverymen', function($query){
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'confirmed', function($query){
                return $query->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed');
            })
            ->when($status == 'pending', function($query) use($data){
                if(config('order_confirmation_model') == 'restaurant' || $data)
                {
                    return $query->where('order_status','pending');
                }
                else
                {
                    return $query->where('order_status','pending')->where('order_type', 'take_away');
                }
            })
            ->when($status == 'cooking', function($query){
                return $query->where('order_status','processing');
            })
            ->when($status == 'food_on_the_way', function($query){
                return $query->where('order_status','picked_up');
            })
            ->when($status == 'delivered', function($query){
                return $query->Delivered();
            })
            ->when($status == 'ready_for_delivery', function($query){
                return $query->where('order_status','handover');
            })
            ->when($status == 'refund_requested', function($query){
                return $query->Refund_requested();
            })
            ->when($status == 'refunded', function($query){
                return $query->Refunded();
            })
            ->when($status == 'scheduled', function($query) use($data){
                return $query->Scheduled()->where(function($q) use($data){
                    if(config('order_confirmation_model') == 'restaurant' || $data)
                    {
                        $q->whereNotIn('order_status',['failed','canceled', 'refund_requested', 'refunded']);
                    }
                    else
                    {
                        $q->whereNotIn('order_status',['pending','failed','canceled', 'refund_requested', 'refunded'])->orWhere(function($query){
                            $query->where('order_status','pending')->where('order_type', 'take_away');
                        });
                    }
                });
            })
            ->when($status == 'all', function($query) use($data){
                return $query->where(function($q1) use($data) {
                    $q1->whereNotIn('order_status',(config('order_confirmation_model') == 'restaurant'|| $data)?['failed','canceled', 'refund_requested', 'refunded']:['pending','failed','canceled', 'refund_requested', 'refunded'])
                    ->orWhere(function($q2){
                        return $q2->where('order_status','pending')->where('order_type', 'take_away');
                    })->orWhere(function($q3){
                        return $q3->where('order_status','pending')->whereNotNull('subscription_id');
                    });
                });
            })
            ->when(in_array($status, ['pending','confirmed']), function($query){
                return $query->OrderScheduledIn(30);
            })
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            })
            ->Notpos()
            ->NotDigitalOrder()
            ->hasSubscriptionToday()
            ->where('restaurant_id',\App\CentralLogics\Helpers::get_restaurant_id())
            ->orderBy('schedule_at', 'desc')
            ->get();

            if (in_array($status, ['requested','rejected','refunded']))
            {
                $data = [
                    'orders'=>$orders,
                    'type'=>$request->order_type ?? translate('messages.all'),
                    'status'=>$status,
                    'order_status'=>isset($request->orderStatus)?implode(', ', $request->orderStatus):null,
                    'search'=>$request->search ?? $key[0] ??null,
                    'from'=>$request->from_date??null,
                    'to'=>$request->to_date??null,
                    'zones'=>isset($request->zone)?Helpers::get_zones_name($request->zone):null,
                    'restaurant'=>Helpers::get_restaurant_name(Helpers::get_restaurant_id()),
                ];

                if ($request->type == 'excel') {
                    return Excel::download(new OrderRefundExport($data), 'RefundOrders.xlsx');
                } else if ($request->type == 'csv') {
                    return Excel::download(new OrderRefundExport($data), 'RefundOrders.csv');
                }
            }


                $data = [
                    'orders'=>$orders,
                    'type'=>$request->order_type ?? translate('messages.all'),
                    'status'=>$status,
                    'order_status'=>isset($request->orderStatus)?implode(', ', $request->orderStatus):null,
                    'search'=>$request->search ?? $key[0] ??null,
                    'from'=>$request->from_date??null,
                    'to'=>$request->to_date??null,
                    'zones'=>isset($request->zone)?Helpers::get_zones_name($request->zone):null,
                    'restaurant'=>Helpers::get_restaurant_name(Helpers::get_restaurant_id()),
                ];

                if ($request->type == 'excel') {
                    return Excel::download(new OrderExport($data), 'Orders.xlsx');
                } else if ($request->type == 'csv') {
                    return Excel::download(new OrderExport($data), 'Orders.csv');
                }

            } catch(\Exception $e) {
                // dd($e);
                Toastr::error("line___{$e->getLine()}",$e->getMessage());
                info(["line___{$e->getLine()}",$e->getMessage(), $e->getFile()]);
                return back();
            }
    }

    public function add_order_proof(Request $request, $id)
    {
        $order = Order::find($id);
        $img_names = $order->order_proof?json_decode($order->order_proof):[];
        $images = [];
        $total_file =  (is_array($request->order_proof) ? count($request->order_proof)  : 0) + count($img_names);
        if(!$img_names){
            $request->validate([
                'order_proof' => 'required|array|max:5',
            ]);
        }

        if ($total_file>5) {
            Toastr::error(translate('messages.order_proof_must_not_have_more_than_5_item'));
            return back();
        }

        if (!empty($request->file('order_proof'))) {
            foreach ($request->order_proof as $img) {
                $image_name = Helpers::upload('order/', 'png', $img);
                array_push($img_names, ['img'=>$image_name, 'storage'=> Helpers::getDisk()]);
            }
            $images = $img_names;
        }

        $order->order_proof = json_encode($images);
        $order->save();

        Toastr::success(translate('messages.order_proof_added'));
        return back();
    }


    public function remove_proof_image(Request $request)
    {
        $order = Order::find($request['id']);
        $array = [];
        $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : [];
        if (count($proof) < 2) {
            Toastr::warning(translate('all_image_delete_warning'));
            return back();
        }
        Helpers::check_and_delete('order/' , $request['image']);
        foreach ($proof as $image) {
            if ($image != $request['name']) {
                array_push($array, $image);
            }
        }
        Order::where('id', $request['id'])->update([
            'order_proof' => json_encode($array),
        ]);
        Toastr::success(translate('order_proof_image_removed_successfully'));
        return back();
    }
    public function download($file_name)
    {
        return Storage::download(base64_decode($file_name));
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json(['message' => translate('messages.deliveryman_not_found')], 404);
        }
        $order = Order::Notpos()->with(['subscription.schedule_today'])->find($order_id);
        $deliveryman = DeliveryMan::where('id', $delivery_man_id)->available()->active()->first();
        if ($order->delivery_man_id == $delivery_man_id) {
            return response()->json(['message' => translate('messages.order_already_assign_to_this_deliveryman')], 400);
        }
        if ($deliveryman) {
            if ($order->delivery_man) {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders > 1 ? $dm->current_orders - 1 : 0;
                // $dm->decrement('assigned_order_count');
                $dm->save();


            $deliveryman_push_notification_status=Helpers::getNotificationStatusData('deliveryman','deliveryman_order_assign_unassign');
                if( $deliveryman_push_notification_status->push_notification_status  == 'active' && $dm->fcm_token){

                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.you_are_unassigned_from_a_order'),
                        'order_id' => '',
                        'image' => '',
                        'type' => 'unassign'
                    ];
                    Helpers::send_push_notif_to_device($dm->fcm_token, $data);

                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'delivery_man_id' => $dm->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }



            }
            $order->delivery_man_id = $delivery_man_id;
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
            $order->accepted = now();
            $order->save();
            OrderLogic::update_subscription_log($order);
            $deliveryman->current_orders = $deliveryman->current_orders + 1;
            $deliveryman->save();
            $deliveryman->increment('assigned_order_count');

            $value = Helpers::text_variable_data_format(
                Helpers::order_status_update_message('accepted',$order->customer? $order->customer->current_language_key:'en'),
                $order->restaurant->name,
                $order->id,
                "{$order->customer->f_name} {$order->customer->l_name}",
                "{$order->delivery_man->f_name} {$order->delivery_man->l_name}"
            );

            try {
                $customer_push_notification_status=Helpers::getNotificationStatusData('customer','customer_order_notification');

                if ($customer_push_notification_status->push_notification_status  == 'active' && $value && $order->customer->cm_firebase_token) {
                    $fcm_token = $order->customer->cm_firebase_token;
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order_status',
                        'order_status' => $order->order_status,
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);

                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $order->customer->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $deliveryman_push_notification_status=Helpers::getNotificationStatusData('deliveryman','deliveryman_order_assign_unassign');
                if( $deliveryman_push_notification_status->push_notification_status  == 'active' && $deliveryman->fcm_token){
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.you_are_assigned_to_a_order'),
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'assign'
                    ];
                    Helpers::send_push_notif_to_device($deliveryman->fcm_token, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'delivery_man_id' => $deliveryman->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

            } catch (\Exception $e) {
                info($e->getMessage());
                Toastr::warning(translate('messages.push_notification_faild'));
            }
            return response()->json([], 200);
        }
        return response()->json(['message' => translate('Deliveryman not available!')], 400);
    }

    public function sync()
    {
        SyncOrdersJob::dispatchSync();
        Toastr::success('Orders Sync completed!');
        return back();
    }

    public function printCanacledOrderItems(Request $request){
        $request->validate([
            'order_date' => 'required|date',
        ]);

        // If date is validate then call the print function
        $printer = new PrintController();
        return $printer->printCanceledOrderItems($request->order_date);
    }
}
