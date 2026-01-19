<?php

namespace App\Events;


use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class myevent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $branch_id;
    public $order_id;
    public $order_html;

    public function __construct($message, $branch_id = null, $order_id = null, $order_html = null)
    {
        $this->message = $message;
        $this->branch_id = $branch_id;
        $this->order_id = $order_id;
        $this->order_html = $order_html;
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
        return ['message' => $this->message, 'branch_id' => $this->branch_id, 'order_id' => $this->order_id, 'order_html' => $this->order_html];
    }
}
