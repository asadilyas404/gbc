<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        Log::info('SyncOrdersJob started (API-based)');

        try {
            $orders = DB::connection('oracle')
                ->table('orders')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            Log::info('Found orders to sync', ['count' => $orders->count()]);

            if ($orders->isEmpty()) {
                Log::info('No orders to sync');
                return;
            }

            $allOrdersData = [];
            $orderIds = [];

            foreach ($orders as $order) {
                $orderDetails = DB::connection('oracle')
                    ->table('order_details')
                    ->where('order_id', $order->id)
                    ->get();

                $orderAdditionalDetails = DB::connection('oracle')
                    ->table('pos_order_additional_dtl')
                    ->where('order_id', $order->id)
                    ->get();

                $kitchenStatus = DB::connection('oracle')
                    ->table('kitchen_order_status_logs')
                    ->where('order_id', $order->id)
                    ->get();

                $allOrdersData[] = [
                    'order' => (array) $order,
                    'order_details' => $orderDetails->map(function ($detail) {
                        return (array) $detail;
                    })->toArray(),
                    'additional_details' => $orderAdditionalDetails->map(function ($detail) {
                        return (array) $detail;
                    })->toArray(),
                    'kitchen_status' => $kitchenStatus->map(function ($status) {
                        return (array) $status;
                    })->toArray(),
                ];

                $orderIds[] = $order->id;
            }

            Log::info('Making bulk API call', ['order_count' => count($allOrdersData)]);

            try {
                $response = Http::timeout(config('services.live_server.timeout', 60))
                    ->withToken(config('services.live_server.token'))
                    ->retry(3, 1000)
                    ->post(config('services.live_server.url') . '/orders/sync-bulk', [
                        'orders' => $allOrdersData
                    ]);

                if ($response->successful()) {
                    DB::connection('oracle')
                        ->table('orders')
                        ->whereIn('id', $orderIds)
                        ->update(['is_pushed' => 'Y']);

                    Log::info('All orders synced successfully via API', [
                        'order_count' => count($orderIds),
                        'order_ids' => $orderIds
                    ]);
                } else {
                    Log::error('Failed syncing orders via API', [
                        'order_count' => count($orderIds),
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('Connection error while syncing orders', [
                    'order_count' => count($orderIds),
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $e) {
                Log::error('Failed syncing orders', [
                    'order_count' => count($orderIds),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            Log::info('SyncOrdersJob completed');

        } catch (\Exception $e) {
            Log::error('SyncOrdersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
