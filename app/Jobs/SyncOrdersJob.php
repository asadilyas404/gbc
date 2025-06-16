<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        \Log::info('SyncOrdersJob started');
        try {
            // Fetch orders that haven't been pushed
            $orders = DB::connection('oracle')
                ->table('orders')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($orders as $order) {
                DB::connection('oracle_target')->beginTransaction();

                try {
                    DB::connection('oracle_target')
                    ->table('orders')
                    ->updateOrInsert(
                        ['id' => $order->id],
                        (array) $order
                    );

                    // Fetch order details for this order

                    $orderDetails = DB::connection('oracle')
                        ->table('order_details')
                        ->where('order_id', $order->id)
                        ->get();

                    foreach ($orderDetails as $detail) {
                        DB::connection('oracle_target')
                        ->table('order_details')
                        ->updateOrInsert(
                            ['id' => $detail->id],
                            (array) $detail
                        );
                    }

                    $orderAdditionalDetails = DB::connection('oracle')
                        ->table('pos_order_additional_dtl')
                        ->where('order_id', $order->id)
                        ->get();

                    foreach ($orderAdditionalDetails as $detail) {
                        DB::connection('oracle_target')
                        ->table('pos_order_additional_dtl')
                        ->updateOrInsert(
                            ['id' => $detail->id],
                            (array) $detail
                        );
                    }

                    // Mark as pushed in source DB

                    DB::connection('oracle')
                        ->table('orders')
                        ->where('id', $order->id)
                        ->update(['is_pushed' => 'Y']);

                    DB::connection('oracle_target')->commit();
                    \Log::info('SyncOrdersJob completed');
                } catch (\Exception $e) {
                    DB::connection('oracle_target')->rollBack();
                    Log::error("Failed syncing order ID {$order->id}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("SyncOrdersJob job failed: " . $e->getMessage());
        }
    }
}
