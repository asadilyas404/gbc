<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 50;

    public $tries = 3;
    public $timeout = 240;
    public $backoff = [10, 30, 60];

    private const SYNC_ENTITY_TYPES = [
        'orders',
        'shift_sessions',
    ];

    public function middleware()
    {
        return [
            (new WithoutOverlapping(
                'sync-orders-' . (string) config('constants.branch_id', 'unknown')
            ))
                ->releaseAfter(15)
                ->expireAfter(600),
        ];
    }

    public function handle()
    {
        set_time_limit($this->timeout + 30);
        $oracle = DB::connection('oracle');

        $orders = $oracle->table('orders')
            ->where(function ($q) {
                $q->where('is_pushed', '!=', 'Y')
                    ->orWhereNull('is_pushed');
            })
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get();

        $shiftSessions = $oracle->table('shift_sessions')
            ->where(function ($q) {
                $q->where('is_pushed', '!=', 'Y')
                    ->orWhereNull('is_pushed');
            })
            ->orderBy('session_id')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($orders->isEmpty() && $shiftSessions->isEmpty()) {
            Log::info('No orders or shift sessions to sync');
            $this->updateLastSyncedRunAt('last_sync_run_at');
            return;
        }

        $orderPayload = $this->buildOrderPayload($orders, $oracle);
        $shiftPayload = $shiftSessions->map(function ($session) {
            return (array) $session;
        })->values()->toArray();

        $orderIds = $orders->pluck('id')->values()->all();
        $shiftSessionIds = $shiftSessions->pluck('session_id')->values()->all();

        Log::info('SyncOrdersJob sending batch', [
            'orders_count' => count($orderPayload),
            'shift_sessions_count' => count($shiftPayload),
        ]);

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying()
                ->retry(3, 1000)
                ->post(config('services.live_server.url') . '/orders/sync-bulk', [
                    'orders' => $orderPayload,
                    'shift_sessions' => $shiftPayload,
                ]);
        } catch (\Throwable $e) {
            Log::error('SyncOrdersJob batch request failed', [
                'error' => $e->getMessage(),
                'orders_count' => count($orderPayload),
                'shift_sessions_count' => count($shiftPayload),
            ]);
            throw $e;
        }

        $responseData = $response->json();

        if (!$response->successful() || !is_array($responseData) || ($responseData['success'] ?? false) !== true) {
            Log::error('SyncOrdersJob batch rejected by live server', [
                'status' => $response->status(),
                'response' => $response->body(),
                'orders_count' => count($orderPayload),
                'shift_sessions_count' => count($shiftPayload),
            ]);

            throw new \RuntimeException('Live server rejected the order sync batch.');
        }

        $successfulOrderIds = $this->successfulIds(
            $orderIds,
            $responseData['order_ids'] ?? null,
            (int) ($responseData['failed'] ?? 0) === 0
                && (!array_key_exists('synced', $responseData)
                    || (int) $responseData['synced'] === count($orderIds))
        );
        $successfulShiftSessionIds = $this->successfulIds(
            $shiftSessionIds,
            $responseData['shift_session_ids'] ?? null,
            (int) ($responseData['shift_sessions_failed'] ?? 0) === 0
                && (!array_key_exists('shift_sessions_synced', $responseData)
                    || (int) $responseData['shift_sessions_synced'] === count($shiftSessionIds))
        );

        if (!empty($successfulOrderIds)) {
            $oracle->table('orders')
                ->whereIn('id', $successfulOrderIds)
                ->update(['is_pushed' => 'Y']);
        }

        if (!empty($successfulShiftSessionIds)) {
            $oracle->table('shift_sessions')
                ->whereIn('session_id', $successfulShiftSessionIds)
                ->update(['is_pushed' => 'Y']);
        }

        $failedOrders = (int) ($responseData['failed'] ?? 0);
        $failedShiftSessions = (int) ($responseData['shift_sessions_failed'] ?? 0);

        $this->updateSyncState(
            $this->successfulRows($orderPayload, $successfulOrderIds),
            'orders'
        );
        $this->updateSyncState(
            $this->successfulRows($shiftPayload, $successfulShiftSessionIds, 'session_id'),
            'shift_sessions'
        );

        if ($failedOrders > 0 || $failedShiftSessions > 0) {
            Log::warning('SyncOrdersJob batch partially failed', [
                'failed_orders' => $failedOrders,
                'failed_shift_sessions' => $failedShiftSessions,
                'successful_orders' => count($successfulOrderIds),
                'successful_shift_sessions' => count($successfulShiftSessionIds),
            ]);

            if (!empty($successfulOrderIds) || !empty($successfulShiftSessionIds)) {
                if ($this->hasPendingRecords($oracle)) {
                    self::dispatch()->onConnection('database')->delay(now()->addSecond());
                }

                return;
            }

            throw new \RuntimeException('Some records in the order sync batch failed.');
        }

        Log::info('SyncOrdersJob batch synced successfully', [
            'orders_count' => count($successfulOrderIds),
            'shift_sessions_count' => count($successfulShiftSessionIds),
        ]);

        if ($this->hasPendingRecords($oracle)) {
            self::dispatch()->onConnection('database')->delay(now()->addSecond());
            return;
        }

        $this->updateLastSyncedRunAt('last_sync_run_at');
        Log::info('SyncOrdersJob completed; no pending records remain');
    }

    private function buildOrderPayload($orders, $oracle): array
    {
        $orderIds = $orders->pluck('id')->values()->all();

        if (empty($orderIds)) {
            return [];
        }

        $details = $this->groupRowsByOrderId(
            $oracle->table('order_details')->whereIn('order_id', $orderIds)->get()
        );
        $additionalDetails = $this->groupRowsByOrderId(
            $oracle->table('pos_order_additional_dtl')->whereIn('order_id', $orderIds)->get()
        );
        $kitchenStatuses = $this->groupRowsByOrderId(
            $oracle->table('kitchen_order_status_logs')->whereIn('order_id', $orderIds)->get()
        );

        return $orders->map(function ($order) use ($details, $additionalDetails, $kitchenStatuses) {
            $key = (string) $order->id;

            return [
                'order' => (array) $order,
                'order_details' => $details[$key] ?? [],
                'additional_details' => $additionalDetails[$key] ?? [],
                'kitchen_status' => $kitchenStatuses[$key] ?? [],
            ];
        })->values()->toArray();
    }

    private function groupRowsByOrderId($rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row->order_id][] = (array) $row;
        }

        return $grouped;
    }

    private function successfulIds(array $inputIds, $responseIds, bool $allSucceeded): array
    {
        if ($allSucceeded) {
            return $inputIds;
        }

        if (!is_array($responseIds)) {
            return [];
        }

        $successful = array_fill_keys(array_map('strval', $responseIds), true);

        return array_values(array_filter($inputIds, function ($id) use ($successful) {
            return isset($successful[(string) $id]);
        }));
    }

    private function successfulRows(array $rows, array $successfulIds, string $idKey = 'id'): array
    {
        $successful = array_fill_keys(array_map('strval', $successfulIds), true);

        return array_values(array_filter($rows, function ($row) use ($successful, $idKey) {
            $id = $idKey === 'id' ? ($row['order']['id'] ?? null) : ($row[$idKey] ?? null);

            return $id !== null && isset($successful[(string) $id]);
        }));
    }

    private function hasPendingRecords($oracle): bool
    {
        return $oracle->table('orders')
                ->where(function ($q) {
                    $q->where('is_pushed', '!=', 'Y')
                        ->orWhereNull('is_pushed');
                })
                ->exists()
            || $oracle->table('shift_sessions')
                ->where(function ($q) {
                    $q->where('is_pushed', '!=', 'Y')
                        ->orWhereNull('is_pushed');
                })
                ->exists();
    }

    public function updateLastSyncedRunAt(string $entityType)
    {
        try {
            DB::table('branch_sync_state')->updateOrInsert(
                [
                    'restaurant_id' => config('constants.branch_id'),
                    'entity_type' => $entityType,
                ],
                [
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            logger()->error("Failed to update last_sync_run_at for {$entityType}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateSyncState(array $chunk, string $entityType, string $dateKey = 'updated_at')
    {
        if (empty($chunk)) {
            return;
        }

        $latestSyncedAt = collect($chunk)
            ->pluck($dateKey)
            ->filter()
            ->sort()
            ->last();

        if (!$latestSyncedAt) {
            return;
        }

        try {
            DB::table('branch_sync_state')->updateOrInsert(
                [
                    'restaurant_id' => config('constants.branch_id'),
                    'entity_type' => $entityType,
                ],
                [
                    'last_synced_at' => Carbon::parse($latestSyncedAt),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            logger()->error("Sync state update failed for {$entityType}", [
                'error' => $e->getMessage(),
            ]);
        }
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
