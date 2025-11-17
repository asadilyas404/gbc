<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerSyncController extends Controller
{
    private const SYNC_ENTITY_TYPES = [
        'customers',
        'order_partners',
    ];

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
            ]);

            $branchId = (int) $validated['branch_id'];
            $cursorMap = $this->resolveLastSyncedMap($branchId);

            $data = [
                'customers' => [],
                'order_partners' => [],
            ];

            $customers = DB::connection('oracle')
                ->table('tbl_sale_customer')
                ->when($cursorMap['customers'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($customers as $customer) {
                $customerRecord = $this->ensureUpdatedAt((array) $customer, 'tbl_sale_customer', 'customer_id');
                $customer->updated_at = $customerRecord['updated_at'] ?? $customer->updated_at;

                $data['customers'][] = $customerRecord;
            }

            $orderPartners = DB::connection('oracle')
                ->table('tbl_sale_order_partners')
                ->when($cursorMap['order_partners'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($orderPartners as $orderPartner) {
                $partnerRecord = $this->ensureUpdatedAt((array) $orderPartner, 'tbl_sale_order_partners', 'partner_id');
                $orderPartner->updated_at = $partnerRecord['updated_at'] ?? $orderPartner->updated_at;

                $data['order_partners'][] = $partnerRecord;
            }

            Log::info('Customer data retrieved for sync', [
                'branch_id' => $branchId,
                'customers_count' => count($data['customers']),
                'order_partners_count' => count($data['order_partners']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'customers' => count($data['customers']),
                    'order_partners' => count($data['order_partners']),
                ],
                'cursor' => array_map(function ($item) {
                    return $item ? $item->toIso8601String() : null;
                }, $this->collectLatestTimestamps($data)),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get customer data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSyncState(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
                'customers_last_synced_at' => 'nullable|string',
                'order_partners_last_synced_at' => 'nullable|string',
            ]);

            $branchId = (int) $validated['branch_id'];
            $timestamps = $this->parseTimestampPayload($validated);
            $this->persistBranchSyncState($branchId, $timestamps);

            Log::info('Branch sync state updated (customers)', [
                'branch_id' => $branchId,
                'payload' => array_intersect_key(
                    $validated,
                    array_flip(array_map(function ($entity) {
                        return $entity . '_last_synced_at';
                    }, self::SYNC_ENTITY_TYPES))
                ),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch sync state updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update branch sync state (customers)', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch sync state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveLastSyncedMap(int $branchId): array
    {
        $rows = $this->fetchBranchSyncState($branchId);

        $map = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $map[$entity] = $rows[$entity] ?? null;
        }

        return $map;
    }

    private function fetchBranchSyncState(int $branchId): array
    {
        $rows = DB::connection('oracle')
            ->table('branch_sync_state')
            ->where('restaurant_id', $branchId)
            ->whereIn('entity_type', self::SYNC_ENTITY_TYPES)
            ->get()
            ->keyBy('entity_type');

        $map = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $timestamp = optional($rows->get($entity))->last_synced_at;
            $map[$entity] = $timestamp ? $this->parseTimestamp($timestamp) : null;
        }

        return $map;
    }

    private function collectLatestTimestamps(array $data): array
    {
        return [
            'customers' => $this->extractMaxTimestamp($data['customers']),
            'order_partners' => $this->extractMaxTimestamp($data['order_partners']),
        ];
    }

    private function extractMaxTimestamp(array $items): ?Carbon
    {
        $max = null;

        foreach ($items as $item) {
            $value = $item['updated_at'] ?? null;
            $timestamp = $this->parseTimestamp($value);

            if (!$timestamp) {
                continue;
            }

            if (!$max || $timestamp->greaterThan($max)) {
                $max = $timestamp;
            }
        }

        return $max;
    }

    private function parseTimestamp(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            Log::warning('Invalid timestamp received during customer sync', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseTimestampPayload(array $validated): array
    {
        $result = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $field = $entity . '_last_synced_at';
            $timestamp = $this->parseTimestamp($validated[$field] ?? null);
            if ($timestamp) {
                $result[$entity] = $timestamp;
            }
        }

        return $result;
    }

    private function persistBranchSyncState(int $branchId, array $timestamps): void
    {
        if (empty($timestamps)) {
            return;
        }

        foreach ($timestamps as $entity => $timestamp) {
            $query = DB::connection('oracle')
                ->table('branch_sync_state')
                ->where('restaurant_id', $branchId)
                ->where('entity_type', $entity);

            $values = [
                'last_synced_at' => $timestamp->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            ];

            if ($query->exists()) {
                $query->update($values);
                continue;
            }

            DB::connection('oracle')
                ->table('branch_sync_state')
                ->insert(array_merge($values, [
                    'restaurant_id' => $branchId,
                    'entity_type' => $entity,
                    'created_at' => Carbon::now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                ]));
        }
    }

    private function ensureUpdatedAt(array $record, string $table, string $primaryKey): array
    {
        if (!array_key_exists($primaryKey, $record)) {
            Log::warning('Missing primary key while ensuring updated_at', [
                'table' => $table,
                'primary_key' => $primaryKey,
            ]);

            return $record;
        }

        if (!empty($record['updated_at'])) {
            return $record;
        }

        $timestamp = Carbon::now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        DB::connection('oracle')
            ->table($table)
            ->where($primaryKey, $record[$primaryKey])
            ->update([
                'updated_at' => $timestamp,
            ]);

        $record['updated_at'] = $timestamp;

        return $record;
    }
}

