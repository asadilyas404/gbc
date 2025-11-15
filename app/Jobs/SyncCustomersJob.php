<?php

namespace App\Jobs;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
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

    private const SYNC_ENTITY_TYPES = [
        'customers',
        'order_partners',
    ];

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncCustomersJob started (API-based)');

        try {
            $branchId = Helpers::get_restaurant_id();

            if (empty($branchId)) {
                Log::warning('SyncCustomersJob halted: branch/restaurant context missing');
                return;
            }

            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/customers/get-data', [
                    'branch_id' => $branchId,
                ]);

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
                'order_partners' => count($data['order_partners'] ?? []),
            ]);

            $syncedCustomerIds = [];
            $syncedOrderPartnerIds = [];
            $latestSyncedTimestamps = array_fill_keys(self::SYNC_ENTITY_TYPES, null);

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
                    $latestSyncedTimestamps['customers'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['customers'],
                        $customer['updated_at'] ?? null
                    );
                    Log::info("Customer ID {$customer['customer_id']} ({$customer['customer_name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing customer ID {$customer['customer_id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['order_partners'] ?? [] as $orderPartner) {
                if (!isset($orderPartner['partner_id'])) {
                    Log::warning('Skipped order partner without partner_id', [
                        'data' => $orderPartner,
                    ]);
                    continue;
                }

                DB::connection('oracle')->beginTransaction();

                try {
                    DB::connection('oracle')
                        ->table('tbl_sale_order_partners')
                        ->updateOrInsert(
                            ['partner_id' => $orderPartner['partner_id']],
                            $orderPartner
                        );

                    DB::connection('oracle')->commit();
                    $syncedOrderPartnerIds[] = $orderPartner['partner_id'];
                    $latestSyncedTimestamps['order_partners'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['order_partners'],
                        $orderPartner['updated_at'] ?? null
                    );
                    $partnerName = $orderPartner['partner_name'] ?? $orderPartner['name'] ?? 'N/A';
                    Log::info("Order partner ID {$orderPartner['partner_id']} ({$partnerName}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    $partnerIdForLog = $orderPartner['partner_id'] ?? 'unknown';
                    Log::error("Failed syncing order partner ID {$partnerIdForLog}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->sendSyncStateUpdate((int) $branchId, $latestSyncedTimestamps);

            Log::info('SyncCustomersJob completed successfully', [
                'synced_customers' => count($syncedCustomerIds),
                'synced_order_partners' => count($syncedOrderPartnerIds),
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

    private function pickLatestTimestamp(?string $current, ?string $candidate): ?string
    {
        if (empty($candidate)) {
            return $current;
        }

        try {
            $candidateCarbon = Carbon::parse($candidate);
        } catch (\Exception $e) {
            Log::warning('Invalid candidate timestamp received during customer sync', [
                'value' => $candidate,
                'error' => $e->getMessage(),
            ]);

            return $current;
        }

        if (empty($current)) {
            return $candidateCarbon->toIso8601String();
        }

        try {
            $currentCarbon = Carbon::parse($current);
        } catch (\Exception $e) {
            Log::warning('Invalid stored timestamp during customer sync, overriding', [
                'value' => $current,
                'error' => $e->getMessage(),
            ]);

            return $candidateCarbon->toIso8601String();
        }

        return $candidateCarbon->greaterThan($currentCarbon)
            ? $candidateCarbon->toIso8601String()
            : $currentCarbon->toIso8601String();
    }

    private function sendSyncStateUpdate(int $branchId, array $latestSyncedTimestamps): void
    {
        $payload = [
            'branch_id' => $branchId,
        ];

        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            if (empty($latestSyncedTimestamps[$entity])) {
                continue;
            }

            try {
                $payload[$entity . '_last_synced_at'] = Carbon::parse($latestSyncedTimestamps[$entity])->toIso8601String();
            } catch (\Exception $e) {
                Log::warning('Invalid timestamp while preparing customer sync update', [
                    'entity' => $entity,
                    'timestamp' => $latestSyncedTimestamps[$entity],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (count($payload) === 1) {
            return;
        }

        try {
            $response = Http::timeout(30)
                ->withToken(config('services.live_server.token'))
                ->post(config('services.live_server.url') . '/customers/update-sync-state', $payload);

            if ($response->successful()) {
                Log::info('Branch sync state updated on live server (customers)', [
                    'branch_id' => $branchId,
                    'entities' => array_keys(array_diff_key($payload, ['branch_id' => null])),
                ]);
            } else {
                Log::warning('Failed to update branch sync state on live server (customers)', [
                    'branch_id' => $branchId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating branch sync state (customers)', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
