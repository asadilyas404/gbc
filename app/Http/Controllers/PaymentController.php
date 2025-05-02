<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\BusinessSetting;

use App\Library\Payer;
use App\Traits\Payment;
use App\Library\Receiver;
use App\Library\Payment as PaymentInfo;


class PaymentController extends Controller
{
// In your Controller constructor
public function __construct()
{
    // Check if the Payment trait exists
    if (trait_exists('App\Traits\Payment')) {
        $this->extendWithPaymentGatewayTrait();
    }
}

// Method to extend with the Payment trait
private function extendWithPaymentGatewayTrait()
{
    // Directly use the trait without eval
    $this->usePaymentGatewayTrait();
}

// Dynamically use the trait
private function usePaymentGatewayTrait()
{
    // Example: Dynamically injecting the trait into a class
    $class = get_class($this);
    if (!in_array('App\Traits\Payment', class_uses($class))) {
        class_uses($class, 'App\Traits\Payment');
    }
}


    private function generateExtendedControllerClass()
    {
        $baseControllerClass = get_class($this);
        $traitClassName = 'App\Traits\Payment';

        $extendedControllerClass = "
            class ExtendedController extends $baseControllerClass {
                use $traitClassName;
            }
        ";

        return $extendedControllerClass;
    }
    public function payment(Request $request)
    {
        if ($request->has('callback')) {
            Order::where(['id' => $request->order_id])->update(['callback' => $request['callback']]);
        }

        session()->put('customer_id', $request['customer_id']);
        session()->put('payment_platform', $request['payment_platform']);
        session()->put('order_id', $request->order_id);

        $order = Order::where(['id' => $request->order_id, 'user_id' => $request['customer_id']])->first();

        if(!$order){
            return response()->json(['errors' => ['code' => 'order-payment', 'message' => 'Data not found']], 403);
        }
        
        //guest user check
        if ($order->is_guest) {
            $address = json_decode($order['delivery_address'] , true);
            $customer = collect([
                'first_name' => $address['contact_person_name'],
                'last_name' => '',
                'phone' => $address['contact_person_number'],
                'email' => '',
            ]);

        } else {
            $customer = User::find($request['customer_id']);
            $customer = collect([
                'first_name' => $customer['f_name'],
                'last_name' => $customer['l_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
            ]);
        }



        if (session()->has('payment_method') == false) {
            session()->put('payment_method', 'ssl_commerz_payment');
        }

        $order_amount = $order->order_amount - $order->partially_paid_amount;

            if (!isset($customer)) {
                return response()->json(['errors' => ['message' => 'Customer not found']], 403);
            }

            if (!isset($order_amount)) {
                return response()->json(['errors' => ['message' => 'Amount not found']], 403);
            }

            if (!$request->has('payment_method')) {
                return response()->json(['errors' => ['message' => 'Payment not found']], 403);
            }

            $payer = new Payer($customer['first_name'].' '.$customer['last_name'], $customer['email'], $customer['phone'], '');

            $currency=BusinessSetting::where(['key'=>'currency'])->first()->value;
            $additional_data = [
                'business_name' => $business_name,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . $business_logo
            ];
            $payment_info = new PaymentInfo(
                'order_place',                               // success_hook
                'order_failed',                              // failure_hook
                $currency,                                   // currency_code
                $request->payment_method,                    // payment_method
                $request['payment_platform'],                // payment_platform
                $request['customer_id'],                     // payer_id
                '100',                                       // receiver_id
                $additional_data,                            // additional_data
                $order_amount,                               // payment_amount
                $request->has('callback') ? $request['callback'] : session('callback'), // external_redirect_link
                'order',                                     // attribute
                $order->id                                   // attribute_id
            );
            

            $receiver_info = new Receiver('receiver_name','example.png');

            $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

            return redirect($redirect_link);


        //for default payment gateway

        if (isset($customer) && isset($order)) {
            $data = [
                'name' => $customer['f_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
            ];
            session()->put('data', $data);
            return view('payment-view');
        }

        return response()->json(['errors' => ['code' => 'order-payment', 'message' => 'Data not found']], 403);

    }


    public function success()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if (isset($order) && $order->callback != null) {
            return redirect($order->callback . '&status=success');
        }
        return response()->json(['message' => 'Payment succeeded'], 200);
    }

    public function fail()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if (isset($order) && $order->callback != null) {
            return redirect($order->callback . '&status=fail');
        }
        return response()->json(['message' => 'Payment failed'], 403);
    }
    public function cancel(Request $request)
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if (isset($order) && $order->callback != null) {
            return redirect($order->callback . '&status=fail');
        }
        return response()->json(['message' => 'Payment failed'], 403);
    }

}
