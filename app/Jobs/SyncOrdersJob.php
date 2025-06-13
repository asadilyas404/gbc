<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncOrdersJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $orders = Order::where('is_pushed', 'N')->get();

        foreach ($orders as $order) {
            try {
                $response = Http::post('https://project-b.com/api/receive-data', $order->toArray());

                if ($response->successful()) {
                    $order->is_pushed = 'Y';
                    $order->save();
                }
            } catch (\Exception $e) {
                \Log::error("Order sync failed: " . $e->getMessage());
            }
        }
    }
}
