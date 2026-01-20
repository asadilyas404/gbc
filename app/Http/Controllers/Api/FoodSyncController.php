<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FoodSyncController extends Controller
{
    private const SYNC_ENTITY_TYPES = [
        'foods',
        'addons',
        'categories',
        'options_list',
    ];

    /**
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFoodData(Request $request)
    {
        try {
            $validated = $request->validate([
                'branch_id' => 'required|integer',
            ]);

            $branchId = (int) $validated['branch_id'];
            $cursorMap = $this->resolveLastSyncedMap($branchId);

            $data = [
                'foods' => [],
                'addons' => [],
                'categories' => [],
                'options_list' => [],
            ];

            $foods = DB::connection('oracle')
                ->table('food')
                ->when($cursorMap['foods'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($foods as $food) {
                $foodRecord = $this->ensureUpdatedAt((array) $food, 'food', 'id');
                $food->updated_at = $foodRecord['updated_at'] ?? $food->updated_at;

                $variations = DB::connection('oracle')
                    ->table('variations')
                    ->where('food_id', $food->id)
                    ->get()
                    ->map(function ($v) {
                        return (array) $v;
                    })
                    ->toArray();

                $variationOptions = DB::connection('oracle')
                    ->table('variation_options')
                    ->where('food_id', $food->id)
                    ->get()
                    ->map(function ($vo) {
                        return (array) $vo;
                    })
                    ->toArray();

                $partnerVariationOptions = DB::connection('oracle')
                    ->table('partner_variation_option')
                    ->where('food_id', $food->id)
                    ->get()
                    ->map(function ($pvo) {
                        return (array) $pvo;
                    })
                    ->toArray();

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $food->id)
                    ->where('translationable_type', 'App\\Models\\Food')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['foods'][] = [
                    'food' => $foodRecord,
                    'variations' => $variations,
                    'variation_options' => $variationOptions,
                    'partner_variation_options' => $partnerVariationOptions,
                    'translations' => $translations,
                ];
            }

            $addons = DB::connection('oracle')
                ->table('add_ons')
                ->when($cursorMap['addons'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($addons as $addon) {
                $addonRecord = $this->ensureUpdatedAt((array) $addon, 'add_ons', 'id');
                $addon->updated_at = $addonRecord['updated_at'] ?? $addon->updated_at;

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $addon->id)
                    ->where('translationable_type', 'App\\Models\\AddOn')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['addons'][] = [
                    'addon' => $addonRecord,
                    'translations' => $translations,
                ];
            }

            $categories = DB::connection('oracle')
                ->table('categories')
                ->when($cursorMap['categories'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($categories as $category) {
                $categoryRecord = $this->ensureUpdatedAt((array) $category, 'categories', 'id');
                $category->updated_at = $categoryRecord['updated_at'] ?? $category->updated_at;

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $category->id)
                    ->where('translationable_type', 'App\\Models\\Category')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['categories'][] = [
                    'category' => $categoryRecord,
                    'translations' => $translations,
                ];
            }

            $optionsList = DB::connection('oracle')
                ->table('options_list')
                ->when($cursorMap['options_list'], function ($query, Carbon $cursor) {
                    $query->where(function ($subQuery) use ($cursor) {
                        $subQuery->where('updated_at', '>', $cursor->toDateTimeString())
                            ->orWhereNull('updated_at');
                    });
                })
                ->orderBy('updated_at')
                ->get();

            foreach ($optionsList as $option) {
                $optionRecord = $this->ensureUpdatedAt((array) $option, 'options_list', 'id');
                $option->updated_at = $optionRecord['updated_at'] ?? $option->updated_at;

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $option->id)
                    ->where('translationable_type', 'App\\Models\\OptionsList')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['options_list'][] = [
                    'option' => $optionRecord,
                    'translations' => $translations,
                ];
            }

            $partnerVariationOptionsCount = array_sum(array_map(function ($food) {
                return count($food['partner_variation_options'] ?? []);
            }, $data['foods']));

            Log::info('Food data retrieved for sync', [
                'branch_id' => $branchId,
                'foods_count' => count($data['foods']),
                'addons_count' => count($data['addons']),
                'categories_count' => count($data['categories']),
                'options_list_count' => count($data['options_list']),
                'partner_variation_options_count' => $partnerVariationOptionsCount,
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'foods' => count($data['foods']),
                    'addons' => count($data['addons']),
                    'categories' => count($data['categories']),
                    'options_list' => count($data['options_list']),
                    'partner_variation_options' => $partnerVariationOptionsCount,
                ],
                'cursor' => array_map(function ($item) {
                    return $item ? $item->toIso8601String() : null;
                }, $this->collectLatestTimestamps($data)),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get food data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve food data',
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
                'foods_last_synced_at' => 'nullable|string',
                'addons_last_synced_at' => 'nullable|string',
                'categories_last_synced_at' => 'nullable|string',
                'options_list_last_synced_at' => 'nullable|string',
            ]);

            $branchId = (int) $validated['branch_id'];
            $timestamps = $this->parseTimestampPayload($validated);
            $this->persistBranchSyncState($branchId, $timestamps);

            Log::info('Branch sync state updated', [
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
        $remoteMap = $this->fetchBranchSyncState($branchId);

        $cursors = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $cursors[$entity] = $remoteMap[$entity] ?? null;
        }

        return $cursors;
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
            'foods' => $this->extractMaxTimestamp($data['foods'], 'food'),
            'addons' => $this->extractMaxTimestamp($data['addons'], 'addon'),
            'categories' => $this->extractMaxTimestamp($data['categories'], 'category'),
            'options_list' => $this->extractMaxTimestamp($data['options_list'], 'option'),
        ];
    }

    private function extractMaxTimestamp(array $items, string $key): ?Carbon
    {
        $max = null;
        foreach ($items as $item) {
            $value = $item[$key]['updated_at'] ?? null;
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
            Log::warning('Invalid timestamp received during sync', [
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

            if ($query->exists()) {
                $query->update([
                    'last_synced_at' => $timestamp->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                continue;
            }

            DB::connection('oracle')
                ->table('branch_sync_state')
                ->insert([
                    'restaurant_id' => $branchId,
                    'entity_type' => $entity,
                    'last_synced_at' => $timestamp->format('Y-m-d H:i:s'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
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

