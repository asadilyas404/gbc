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
            // 1) Get shift sessions to sync
            $shiftSessions = DB::connection('oracle')
                ->table('shift_sessions')
                ->where(function ($q) {
                    $q->where('is_pushed', '!=', 'Y')
                      ->orWhereNull('is_pushed');
                })
                ->get();

            $shiftSessionsPayload = $shiftSessions->map(function ($session) {
                return (array) $session;
            })->toArray();

            Log::info('Found shift sessions to sync', [
                'count' => $shiftSessions->count(),
            ]);

            // 2) Get orders to sync
            $orders = DB::connection('oracle')
                ->table('orders')
                ->where(function ($q) {
                    $q->where('is_pushed', '!=', 'Y')
                      ->orWhereNull('is_pushed');
                })
                ->get();

            Log::info('Found orders to sync', [
                'count' => $orders->count(),
            ]);

            if ($orders->isEmpty() && $shiftSessions->isEmpty()) {
                Log::info('No orders or shift sessions to sync');
                return;
            }

            // 3) Build allOrdersData payload
            $allOrdersData = [];

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
                    'order'             => (array) $order,
                    'order_details'     => $orderDetails->map(fn($detail) => (array) $detail)->toArray(),
                    'additional_details'=> $orderAdditionalDetails->map(fn($detail) => (array) $detail)->toArray(),
                    'kitchen_status'    => $kitchenStatus->map(fn($status) => (array) $status)->toArray(),
                ];
            }

            Log::info('Preparing chunked bulk API calls', [
                'order_count'          => count($allOrdersData),
                'shift_session_count'  => count($shiftSessionsPayload),
            ]);

            // 4) Chunking
            $chunkSize          = 50; // tune this as needed
            $orderChunks        = array_chunk($allOrdersData, $chunkSize);
            $shiftSessionChunks = array_chunk($shiftSessionsPayload, $chunkSize);

            $maxChunks = max(count($orderChunks), count($shiftSessionChunks));

            for ($index = 0; $index < $maxChunks; $index++) {
                $ordersChunk = $orderChunks[$index] ?? [];
                $shiftChunk  = $shiftSessionChunks[$index] ?? [];

                // Extract order IDs from nested structure
                $orderIds = array_map(function ($item) {
                    return $item['order']['id'];
                }, $ordersChunk);

                // Extract shift session IDs
                $shiftSessionIds = array_map(function ($ss) {
                    return $ss['session_id'];
                }, $shiftChunk);

                try {
                    $response = Http::timeout(config('services.live_server.timeout', 60))
                        ->withToken(config('services.live_server.token'))
                        ->withoutVerifying()
                        ->retry(3, 1000)
                        ->post(config('services.live_server.url') . '/orders/sync-bulk', [
                            'orders'         => $ordersChunk ?? [], // can be []
                            'shift_sessions' => $shiftChunk ?? [],   // can be []
                        ]);

                    if ($response->successful()) {
                        // Mark orders pushed
                        if (!empty($orderIds)) {
                            DB::connection('oracle')
                                ->table('orders')
                                ->whereIn('id', $orderIds)
                                ->update(['is_pushed' => 'Y']);
                        }

                        // Mark shift sessions pushed
                        if (!empty($shiftSessionIds)) {
                            DB::connection('oracle')
                                ->table('shift_sessions')
                                ->whereIn('session_id', $shiftSessionIds)
                                ->update(['is_pushed' => 'Y']);
                        }

                        Log::info('Orders chunk synced successfully', [
                            'chunk'                  => $index + 1,
                            'orders_count'           => count($orderIds),
                            'shift_sessions_count'   => count($shiftSessionIds),
                            'order_ids'              => $orderIds,
                            'session_ids'            => $shiftSessionIds,
                        ]);
                    } else {
                        Log::error('Failed syncing orders chunk', [
                            'chunk'                  => $index + 1,
                            'status'                 => $response->status(),
                            'response'               => $response->body(),
                            'orders_count'           => count($orderIds),
                            'shift_sessions_count'   => count($shiftSessionIds),
                        ]);
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::error('Connection error syncing chunk', [
                        'chunk' => $index + 1,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Unexpected error syncing chunk', [
                        'chunk' => $index + 1,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
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
