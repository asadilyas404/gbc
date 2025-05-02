<?php

namespace App\Http\Controllers;

use App\CentralLogics\CustomerLogic;
use App\Models\Order;
use App\Models\BusinessSetting;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;

class WalletPaymentController extends Controller
{
    /**
     * make_payment Rave payment process
     * @return void
     */
    public function make_payment(Request $request)
    {
        // Check if wallet status is enabled
        $walletStatus = BusinessSetting::where('key', 'wallet_status')->first();
        if (!$walletStatus || $walletStatus->value != 1) {
            Toastr::error(translate('messages.customer_wallet_disable_warning'));
            return back();  // Return to the previous page if wallet is disabled
        }
    
        // Get the order for the user
        $order = Order::with('customer')->where(['id' => $request->order_id, 'user_id' => $request->user_id])->first();
        if (!$order) {
            Toastr::error(translate('messages.order_not_found'));
            return back();  // Return if the order is not found
        }
    
        // Check if customer has sufficient wallet balance
        if ($order->customer->wallet_balance < $order->order_amount) {
            Toastr::error(translate('messages.insufficient_balance'));
            return back();  // Return if balance is insufficient
        }
    
// Create wallet transaction
$transaction = CustomerLogic::create_wallet_transaction(
    $order->user_id, // user_id
    $order->order_amount, // amount
    'order_place', // transaction_type
    $order->id // referance
);

    
        // If transaction is successful, update order
        if ($transaction !== false) {
            try {
                $order->transaction_reference = $transaction->transaction_id;
                $order->payment_method = 'wallet';
                $order->payment_status = 'paid';
                $order->order_status = 'confirmed';
                $order->confirmed = now();
                $order->save();  // Save order details
    
                // Send order notification
                Helpers::send_order_notification($order);
    
                // Check for callback URL
                if ($order->callback != null) {
                    return redirect($order->callback . '&status=success');
                } else {
                    return redirect()->route('payment-success');
                }
            } catch (\Exception $e) {
                info($e->getMessage());
                Toastr::error(translate('messages.payment_error'));
                return back();  // Return if there's an error during order update
            }
        } else {
            // Handle payment failure scenario
            $order->payment_method = 'wallet';
            $order->order_status = 'failed';
            $order->failed = now();
            $order->save();  // Save order as failed
    
            // Check for callback URL
            if ($order->callback != null) {
                return redirect($order->callback . '&status=fail');
            } else {
                return redirect()->route('payment-fail');
            }
        }
    }
}    
