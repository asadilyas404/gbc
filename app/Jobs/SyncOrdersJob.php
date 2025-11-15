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

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncOrdersJob started (API-based)');

        try {
            $shiftSessions = DB::connection('oracle')
                ->table('shift_sessions')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            $shiftSessionsPayload = $shiftSessions->map(function ($session) {
                return (array) $session;
            })->toArray();

            $shiftSessionIds = $shiftSessions->pluck('session_id')->toArray();

            Log::info('Found shift sessions to sync', ['count' => $shiftSessions->count()]);

            $orders = DB::connection('oracle')
                ->table('orders')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            Log::info('Found orders to sync', ['count' => $orders->count()]);

            if ($orders->isEmpty() && $shiftSessions->isEmpty()) {
                Log::info('No orders or shift sessions to sync');
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

            Log::info('Making bulk API call', [
                'order_count' => count($allOrdersData),
                'shift_session_count' => count($shiftSessionsPayload)
            ]);

            try {
                $response = Http::timeout(config('services.live_server.timeout', 60))
                    ->withToken(config('services.live_server.token'))
                    ->withoutVerifying() // disables SSL certificate verification
                    ->retry(3, 1000)
                    ->post(config('services.live_server.url') . '/orders/sync-bulk', [
                        'orders' => $allOrdersData,
                        'shift_sessions' => $shiftSessionsPayload
                    ]);

                if ($response->successful()) {
                    if (!empty($orderIds)) {
                        DB::connection('oracle')
                            ->table('orders')
                            ->whereIn('id', $orderIds)
                            ->update(['is_pushed' => 'Y']);

                        Log::info('Orders synced successfully via API', [
                            'order_count' => count($orderIds),
                            'order_ids' => $orderIds
                        ]);
                    }

                    if (!empty($shiftSessionIds)) {
                        DB::connection('oracle')
                            ->table('shift_sessions')
                            ->whereIn('session_id', $shiftSessionIds)
                            ->update(['is_pushed' => 'Y']);

                        Log::info('Shift sessions synced successfully via orders API', [
                            'session_count' => count($shiftSessionIds),
                            'session_ids' => $shiftSessionIds
                        ]);
                    }
                } else {
                    Log::error('Failed syncing orders via API', [
                        'order_count' => count($orderIds),
                        'shift_session_count' => count($shiftSessionIds),
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
