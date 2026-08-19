<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class myevent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $branch_id;
    public $order_id;
    public $order_html;
    public $order_status;
    public $payment_status;
    public $order_type;
    public $event_type;

    public function __construct($message, $branch_id = null, $order_id = null, $order_html = null, $order_status = null, $payment_status = null, $order_type = null, $event_type = 'created')
    {
        $this->message = $message;
        $this->branch_id = $branch_id;
        $this->order_id = $order_id;
        $this->order_html = $order_html;
        $this->order_status = $order_status;
        $this->payment_status = $payment_status;
        $this->order_type = $order_type;
        $this->event_type = $event_type;
    }

    public function broadcastOn()
    {
        return new Channel('my-channel');
    }

    public function broadcastAs()
    {
        return 'my-event';
    }

    public function broadcastWith()
    {
        $payload = [
            'message' => $this->message,
            'branch_id' => $this->branch_id,
            'order_id' => $this->order_id,
            'order_status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'order_type' => $this->order_type,
            'event_type' => $this->event_type,
        ];
        \Illuminate\Support\Facades\Log::info('[WebSocket] myevent dispatched:', $payload);
        return $payload;
    }
}
