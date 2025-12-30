<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchRestaurantSyncController extends Controller
{
    private const SYNC_ENTITY_TYPES = [
        'branches',
        'restaurants',
        'partners',
        'banks',
        'vendors',
    ];

    /**
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
                'snapshot' => 'nullable|boolean',
            ]);

            $branchId = (int) $validated['branch_id'];
            $snapshot = $validated['snapshot'] ?? false;
            $cursorMap = $this->resolveLastSyncedMap($branchId);

            $data = [
                'branches' => [],
                'restaurants' => [],
                'partners'  => [],
                'banks'    => [],
            ];

            $branches = DB::connection('oracle')
                ->table('tbl_soft_branch')
                ->when(!$snapshot && $cursorMap['branches'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($branches as $branch) {
                $branchRecord = $this->ensureUpdatedAt((array) $branch, 'tbl_soft_branch', 'branch_id');
                $branch->updated_at = $branchRecord['updated_at'] ?? $branch->updated_at;

                $data['branches'][] = $branchRecord;
            }

            $restaurants = DB::connection('oracle')
                ->table('restaurants')
                ->when(!$snapshot && $cursorMap['restaurants'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($restaurants as $restaurant) {
                $restaurantRecord = $this->ensureUpdatedAt((array) $restaurant, 'restaurants', 'id');
                $restaurant->updated_at = $restaurantRecord['updated_at'] ?? $restaurant->updated_at;

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $restaurant->id)
                    ->where('translationable_type', 'App\\Models\\Restaurant')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['restaurants'][] = [
                    'restaurant' => $restaurantRecord,
                    'translations' => $translations,
                ];
            }

            $partners = DB::connection('oracle')
                ->table('tbl_sale_order_partners')
                ->when(!$snapshot && $cursorMap['partners'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($partners as $partner) {
                $partnerRecord = $this->ensureUpdatedAt((array) $partner, 'tbl_sale_order_partners', 'partner_id');
                $partner->updated_at = $partnerRecord['updated_at'] ?? $partner->updated_at;

                $data['partners'][] = $partnerRecord;
            }

            $banks = DB::connection('oracle')
                ->table('tbl_defi_bank')
                ->when(!$snapshot && $cursorMap['banks'] ?? null, function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();
            
            foreach ($banks as $bank) {
                $bankRecord = $this->ensureUpdatedAt((array) $bank, 'tbl_defi_bank', 'bank_id');
                $bank->updated_at = $bankRecord['updated_at'] ?? $bank->updated_at;

                $data['banks'][] = $bankRecord;
            }

            $vendors = DB::connection('oracle')
                ->table('vendors')
                ->when(!$snapshot && $cursorMap['vendors'] ?? null, function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($vendors as $vendor) {
                $vendorRecord = $this->ensureUpdatedAt((array) $vendor, 'vendors', 'id');
                $vendor->updated_at = $vendorRecord['updated_at'] ?? $vendor->updated_at;

                $data['vendors'][] = $vendorRecord;
            }

            Log::info('Branch, restaurant and partner data retrieved for sync', [
                'branch_id' => $branchId,
                'branches_count' => count($data['branches']),
                'restaurants_count' => count($data['restaurants']),
                'partners_count' => count($data['partners']),
                'banks_count' => count($data['banks']),
                'vendors_count' => count($vendors),
            
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'branches' => count($data['branches']),
                    'restaurants' => count($data['restaurants']),
                    'partners' => count($data['partners']),
                    'banks' => count($data['banks']),
                    'vendors' => count($data['vendors']),
                ],
                'cursor' => array_map(function ($item) {
                    return $item ? $item->toIso8601String() : null;
                }, $this->collectLatestTimestamps($data)),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get branch/restaurant data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch/restaurant data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSyncState(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
                'branches_last_synced_at' => 'nullable|string',
                'restaurants_last_synced_at' => 'nullable|string',
                'partners_last_synced_at' => 'nullable|string',
                'banks_last_synced_at' => 'nullable|string',
                'vendors_last_synced_at' => 'nullable|string',
            ]);

            $branchId = (int) $validated['branch_id'];
            $timestamps = $this->parseTimestampPayload($validated);
            $this->persistBranchSyncState($branchId, $timestamps);

            Log::info('Branch sync state updated for branches/restaurants/partners/banks/vendors', [
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
            Log::error('Failed to update branch sync state', [
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
            'branches' => $this->extractMaxTimestamp($data['branches']),
            'restaurants' => $this->extractMaxTimestamp($data['restaurants'], 'restaurant'),
        ];
    }

    private function extractMaxTimestamp(array $items, string $key = null): ?Carbon
    {
        $max = null;

        foreach ($items as $item) {
            $source = $key ? ($item[$key] ?? []) : $item;
            $value = $source['updated_at'] ?? null;
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
            Log::warning('Invalid timestamp received during branch sync', [
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
                'last_synced_at' => $timestamp->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
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
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
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

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

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

