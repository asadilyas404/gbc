<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Order;
use App\Models\DeliveryMan;
use App\Exports\OrderExport;
use App\Models\OrderPayment;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Exports\OrderRefundExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SyncOrdersJob;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function list($status , Request $request)
    {
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
            return $query->where(function($q1) use($data) {
                $q1->whereNotIn('order_status',(config('order_confirmation_model') == 'restaurant'|| $data)?['failed', 'refund_requested', 'refunded']:['pending','failed', 'refund_requested', 'refunded'])
                ->orWhere(function($q2){
                    return $q2->where('order_status','pending')->where('order_type', 'take_away');
                })->orWhere(function($q3){
                    return $q3->where('order_status','pending')->whereNotNull('subscription_id');
                });
            });
        })
        ->when($status == 'draft', function($query){
            return $query->where('payment_status','unpaid');
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
        // ->NotDigitalOrder()
        ->hasSubscriptionToday()
        ->where('restaurant_id',\App\CentralLogics\Helpers::get_restaurant_id())
        ->orderBy('schedule_at', 'desc')
        ->paginate(config('default_pagination'));

        $st=$status;
        $status = translate('messages.'.$status);
        return view('vendor-views.order.list', compact('orders', 'status','st'));
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

    public function quickView($id)
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

                $html .= '<tr>
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

            $cart[] = [
                'id' => $item->food_id,
                'name' => $food['name'] ?? '',
                'quantity' => $item->quantity,
                'price' => $item->price + $variation_price,
                'variation_price' => $variation_price,
                'variant' => '',
                'variations' => $simplified_variations,
                'variation_option_ids' => '',
                'add_ons' => json_decode($item->add_ons, true) ?? [],
                'add_on_qtys' => [],
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

        // Store in session
        session()->put('cart', collect($cart));
        session()->put('editing_order_id', $order->id);

        Toastr::success('Unpaid order loaded to cart.');

        // Return payment data
        return response()->json([
            'total_amount_formatted' => Helpers::format_currency($order->pos_details->invoice_amount ?? 0),
            'customer_name' => $order->pos_details->customer_name ?? '',
            'car_number' => $order->pos_details->car_number ?? '',
            'phone' => $order->pos_details->phone ?? '',
            'cash_paid' => $order->pos_details->cash_paid ?? 0,
            'card_paid' => $order->pos_details->card_paid ?? 0,
            'delivery_type' => $order->order_type ?? '',
            'bank_account' => $order->pos_details->bank_account ?? '',
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['error' => 'Order not found.'], 404);
    } catch (\Throwable $e) {
        \Log::error('Error in getPaymentData: ' . $e->getMessage(), [
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
        ->map(fn($detail) => $detail->food->est_make_time ?? 0)
        ->max();
        return view('vendor-views.order.receipt', compact('order', 'maxMakeTime'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id, 'restaurant_id' => Helpers::get_restaurant_id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success('Payment reference code is added!');
        return back();
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
                info(["line___{$e->getLine()}",$e->getMessage()]);
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
        SyncOrdersJob::dispatch();
        Toastr::success('Orders Sync completed!');
        return back();
    }
}
