<?php

namespace App\Jobs;

use App\Models\Log;
use App\Models\Order;
use App\Models\OrderWhatsappMsgLog;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class POSOrderReceived implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public int $orderId;
    public string $state;

    public int $tries = 3;
    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'pos-order-whatsapp-' . $this->orderId . '-' . $this->phone . '-' . $this->state;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(string $phone, int $orderId, string $state = 'new')
    {
        $this->phone = $phone;
        $this->orderId = $orderId;
        $this->state = $state;
    }

    public function handle(): void
    {
        try {
            $order = Order::with([
                'restaurant',
                'restaurant.translations',
                'details.food',
                'takenBy',
                'pos_details',
                'partner',
            ])->findOrFail($this->orderId);


            if($this->state == 'new' && !empty($order->whatsapp_confirmation_sent_at)){
                return;
            }

            $wService = new WhatsappService();

            $response = $wService->sendOrderConfirmationMessage(
                $this->phone,
                $order,
                $this->state
            );

            OrderWhatsappMsgLog::create([
                'order_id' => $order->id,
                'message_status' => 'success',
                'message_type' => $this->state == 'new' ? 'order_creation' : 'order_modification',
                'order_amount' => $order->order_amount,
                'branch_id' => config('constants.branch_id'),
                'phone' => $this->phone
            ]);

            \Log::info('Whatsapp Message Sent', [
                'phone' => $this->phone,
                'order_id' => $this->orderId,
                'state' => $this->state,
                'response' => $response,
            ]);

        } catch (Throwable $e) {
            \Log::error('Whatsapp Message Send Error: ' . $e->getMessage(), [
                'phone' => $this->phone,
                'order_id' => $this->orderId,
                'state' => $this->state,
                'attempt' => $this->attempts(),
            ]);

            $order = Order::find($this->orderId);
            OrderWhatsappMsgLog::create([
                'order_id' => $order->id,
                'message_status' => 'failed',
                'message_type' => $this->state == 'new' ? 'order_creation' : 'order_modification',
                'order_amount' => $order->order_amount,
                'branch_id' => config('constants.branch_id'),
                'message_exception' => $e->getMessage(),
                'phone' => $this->phone
            ]);

            if ($this->isTemporaryWhatsappError($e)) {
                throw $e;
            }

            $this->fail($e);
        }
    }

    private function isTemporaryWhatsappError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'server error')
            || str_contains($message, '500')
            || str_contains($message, '502')
            || str_contains($message, '503')
            || str_contains($message, '504');
    }

    public function failed(Throwable $e): void
    {
        \Log::error('Whatsapp Message Job Permanently Failed', [
            'phone' => $this->phone,
            'order_id' => $this->orderId,
            'state' => $this->state,
            'error' => $e->getMessage(),
        ]);

        $order = Order::find($this->orderId);

        OrderWhatsappMsgLog::create([
            'order_id' => $order->id,
            'message_status' => 'failed',
            'message_type' => $this->state == 'new' ? 'order_creation' : 'order_modification',
            'order_amount' => $order->order_amount,
            'branch_id' => config('constants.branch_id'),
            'message_exception' => $e->getMessage(),
            'phone' => $this->phone
        ]);

    }
}