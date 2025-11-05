<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SyncCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncCustomersJob started (API-based)');

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/customers/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get customer data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received customer data from live server', [
                'customers' => count($data['customers'] ?? []),
            ]);

            $syncedCustomerIds = [];

            foreach ($data['customers'] ?? [] as $customer) {
                DB::connection('oracle')->beginTransaction();

                try {
                    DB::connection('oracle')
                        ->table('tbl_sale_customer')
                        ->updateOrInsert(
                            ['customer_id' => $customer['customer_id']],
                            $customer
                        );

                    DB::connection('oracle')->commit();
                    $syncedCustomerIds[] = $customer['customer_id'];
                    Log::info("Customer ID {$customer['customer_id']} ({$customer['customer_name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing customer ID {$customer['customer_id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!empty($syncedCustomerIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->post(config('services.live_server.url') . '/customers/mark-pushed', [
                            'customer_ids' => $syncedCustomerIds,
                        ]);

                    if ($markResponse->successful()) {
                        Log::info('Customers marked as pushed on live server');
                    } else {
                        Log::warning('Failed to mark customers as pushed on live server', [
                            'status' => $markResponse->status()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to mark customers as pushed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('SyncCustomersJob completed successfully', [
                'synced_customers' => count($syncedCustomerIds),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing customers', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncCustomersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
