<?php

namespace App\Jobs;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncFoodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SYNC_ENTITY_TYPES = [
        'foods',
        'addons',
        'categories',
        'options_list',
    ];

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncFoodJob started (API-based)');

        try {
            $branchId = Helpers::get_restaurant_id();

            if (empty($branchId)) {
                Log::warning('SyncFoodJob halted: branch/restaurant context missing');
                return;
            }

            $lastSyncedAtMap = $this->getLastSyncedAtMap((int) $branchId);
            $requestParams = $this->buildRequestParams((int) $branchId, $lastSyncedAtMap);

            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying() // disables SSL certificate verification
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/food/get-data', $requestParams);

            if (!$response->successful()) {
                Log::error('Failed to get food data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            $partnerVariationOptionsCount = array_sum(array_map(function ($foodData) {
                return count($foodData['partner_variation_options'] ?? []);
            }, $data['foods'] ?? []));

            Log::info('Received food data from live server', [
                'foods' => count($data['foods'] ?? []),
                'addons' => count($data['addons'] ?? []),
                'categories' => count($data['categories'] ?? []),
                'options_list' => count($data['options_list'] ?? []),
                'partner_variation_options' => $partnerVariationOptionsCount,
            ]);

            $syncedFoodIds = [];
            $syncedAddonIds = [];
            $syncedCategoryIds = [];
            $syncedOptionsListIds = [];
            $syncedPartnerVariationOptionCount = 0;
            $latestSyncedTimestamps = array_fill_keys(self::SYNC_ENTITY_TYPES, null);

            foreach ($data['foods'] ?? [] as $foodData) {
                DB::connection('oracle')->beginTransaction();

                try {
                    $food = $foodData['food'];

                    DB::connection('oracle')
                        ->table('food')
                        ->updateOrInsert(
                            ['id' => $food['id']],
                            $food
                        );

                    if (!empty($food['image'])) {
                        $this->copyImageFromStorage($food['image'], 'product/');
                    }

                    foreach ($foodData['translations'] ?? [] as $translation) {
                        DB::connection('oracle')
                            ->table('translations')
                            ->updateOrInsert(
                                ['id' => $translation['id']],
                                $translation
                            );
                    }

                    foreach ($foodData['variations'] ?? [] as $variation) {
                        DB::connection('oracle')
                            ->table('variations')
                            ->updateOrInsert(
                                ['id' => $variation['id']],
                                $variation
                            );
                    }

                    foreach ($foodData['variation_options'] ?? [] as $option) {
                        DB::connection('oracle')
                            ->table('variation_options')
                            ->updateOrInsert(
                                ['id' => $option['id']],
                                $option
                            );
                    }

                    foreach ($foodData['partner_variation_options'] ?? [] as $partnerOption) {
                        if (!isset($partnerOption['id'])) {
                            Log::warning('Skipped partner variation option without ID', [
                                'food_id' => $food['id'] ?? null,
                                'data' => $partnerOption,
                            ]);
                            continue;
                        }

                        DB::connection('oracle')
                            ->table('partner_variation_option')
                            ->updateOrInsert(
                                ['id' => $partnerOption['id']],
                                $partnerOption
                            );

                        $syncedPartnerVariationOptionCount++;
                    }

                    DB::connection('oracle')->commit();
                    $syncedFoodIds[] = $food['id'];
                    $latestSyncedTimestamps['foods'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['foods'],
                        $food['updated_at'] ?? null
                    );

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing food ID {$foodData['food']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['addons'] ?? [] as $addonData) {
                try {
                    $addon = $addonData['addon'];

                    DB::connection('oracle')
                        ->table('add_ons')
                        ->updateOrInsert(
                            ['id' => $addon['id']],
                            $addon
                        );

                    foreach ($addonData['translations'] ?? [] as $translation) {
                        DB::connection('oracle')
                            ->table('translations')
                            ->updateOrInsert(
                                ['id' => $translation['id']],
                                $translation
                            );
                    }

                    $syncedAddonIds[] = $addon['id'];
                    $latestSyncedTimestamps['addons'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['addons'],
                        $addon['updated_at'] ?? null
                    );

                } catch (\Exception $e) {
                    Log::error("Failed syncing AddOn ID {$addonData['addon']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['categories'] ?? [] as $categoryData) {
                try {
                    $category = $categoryData['category'];

                    DB::connection('oracle')
                        ->table('categories')
                        ->updateOrInsert(
                            ['id' => $category['id']],
                            $category
                        );

                    if (!empty($category['image'])) {
                        $this->copyImageFromStorage($category['image'], 'category/');
                    }

                    foreach ($categoryData['translations'] ?? [] as $translation) {
                        DB::connection('oracle')
                            ->table('translations')
                            ->updateOrInsert(
                                ['id' => $translation['id']],
                                $translation
                            );
                    }

                    $syncedCategoryIds[] = $category['id'];
                    $latestSyncedTimestamps['categories'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['categories'],
                        $category['updated_at'] ?? null
                    );

                } catch (\Exception $e) {
                    Log::error("Failed syncing Category ID {$categoryData['category']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['options_list'] ?? [] as $optionData) {
                try {
                    $option = $optionData['option'];

                    DB::connection('oracle')
                        ->table('options_list')
                        ->updateOrInsert(
                            ['id' => $option['id']],
                            $option
                        );

                    foreach ($optionData['translations'] ?? [] as $translation) {
                        DB::connection('oracle')
                            ->table('translations')
                            ->updateOrInsert(
                                ['id' => $translation['id']],
                                $translation
                            );
                    }

                    $syncedOptionsListIds[] = $option['id'];
                    $latestSyncedTimestamps['options_list'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['options_list'],
                        $option['updated_at'] ?? null
                    );

                } catch (\Exception $e) {
                    Log::error("Failed syncing OptionsList ID {$optionData['option']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!empty($syncedFoodIds) || !empty($syncedAddonIds) || !empty($syncedCategoryIds) || !empty($syncedOptionsListIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->post(config('services.live_server.url') . '/food/mark-pushed', [
                            'food_ids' => $syncedFoodIds,
                            'addon_ids' => $syncedAddonIds,
                            'category_ids' => $syncedCategoryIds,
                            'options_list_ids' => $syncedOptionsListIds,
                        ]);

                    if ($markResponse->successful()) {
                        Log::info('Items marked as pushed on live server');
                    } else {
                        Log::warning('Failed to mark items as pushed on live server', [
                            'status' => $markResponse->status()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to mark items as pushed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('SyncFoodJob completed successfully', [
                'synced_foods' => count($syncedFoodIds),
                'synced_addons' => count($syncedAddonIds),
                'synced_categories' => count($syncedCategoryIds),
                'synced_options_list' => count($syncedOptionsListIds),
                'synced_partner_variation_options' => $syncedPartnerVariationOptionCount,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing food', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncFoodJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function copyImageFromStorage($filename, $folder = 'product/')
    {
        $imageSourceBase = config('constants.image_source_base');
        $imageSourceBase = rtrim($imageSourceBase, '/') . '/';
        $relativePath = $folder . $filename;

        $sourceUrl = $imageSourceBase . $relativePath;
        $destinationPath = public_path('storage/' . $relativePath);

        try {
            if (!file_exists(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0755, true);
            }

            $imageData = @file_get_contents($sourceUrl);
            if ($imageData === false) {
                return;
            }

            file_put_contents($destinationPath, $imageData);

        } catch (\Exception $e) {
            Log::error("Image copy failed for {$relativePath}: " . $e->getMessage());
        }
    }

    private function buildRequestParams(int $branchId, array $lastSyncedAtMap): array
    {
        $params = [
            'branch_id' => $branchId,
        ];

        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            if (empty($lastSyncedAtMap[$entity])) {
                continue;
            }

            try {
                $params[$entity . '_last_synced_at'] = Carbon::parse($lastSyncedAtMap[$entity])->toIso8601String();
            } catch (\Exception $e) {
                Log::warning("Invalid stored last_synced_at for {$entity}, skipping from request", [
                    'value' => $lastSyncedAtMap[$entity],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $params;
    }

    private function getLastSyncedAtMap(int $branchId): array
    {
        $rows = DB::connection('oracle')
            ->table('branch_sync_state')
            ->where('restaurant_id', $branchId)
            ->whereIn('entity_type', self::SYNC_ENTITY_TYPES)
            ->get()
            ->keyBy('entity_type');

        $map = [];
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            $map[$entity] = optional($rows->get($entity))->last_synced_at;
        }

        return $map;
    }

    private function pickLatestTimestamp(?string $current, ?string $candidate): ?string
    {
        if (empty($candidate)) {
            return $current;
        }

        try {
            $candidateCarbon = Carbon::parse($candidate);
        } catch (\Exception $e) {
            Log::warning('Invalid candidate timestamp received during sync', [
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
            Log::warning('Invalid current timestamp stored locally, overriding', [
                'value' => $current,
                'error' => $e->getMessage(),
            ]);

            return $candidateCarbon->toIso8601String();
        }

        return $candidateCarbon->greaterThan($currentCarbon)
            ? $candidateCarbon->toIso8601String()
            : $currentCarbon->toIso8601String();
    }

    private function persistSyncedTimestamps(int $branchId, array $latestSyncedTimestamps): void
    {
        foreach (self::SYNC_ENTITY_TYPES as $entity) {
            if (empty($latestSyncedTimestamps[$entity])) {
                continue;
            }

            $this->upsertLastSyncedAt($branchId, $entity, $latestSyncedTimestamps[$entity]);
        }
    }

    private function upsertLastSyncedAt(int $branchId, string $entity, string $timestamp): void
    {
        try {
            $normalizedTimestamp = Carbon::parse($timestamp)->utc()->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Unable to persist last_synced_at due to invalid timestamp', [
                'entity' => $entity,
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $now = Carbon::now()->utc()->format('Y-m-d H:i:s');

        $query = DB::connection('oracle')
            ->table('branch_sync_state')
            ->where('restaurant_id', $branchId)
            ->where('entity_type', $entity);

        if ($query->exists()) {
            $query->update([
                'last_synced_at' => $normalizedTimestamp,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::connection('oracle')
            ->table('branch_sync_state')
            ->insert([
                'restaurant_id' => $branchId,
                'entity_type' => $entity,
                'last_synced_at' => $normalizedTimestamp,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
