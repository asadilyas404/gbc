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

        $branchId = config('constants.branch_id');

        if (empty($branchId)) {
            Log::warning('SyncFoodJob halted: branch/restaurant context missing');
            return;
        }

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying()
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/food/get-data', [
                    'branch_id' => $branchId,
                    // If you later add snapshot mode on API:
                    // 'snapshot' => 1,
                ]);

            if (!$response->successful()) {
                Log::error('Failed to get food data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return;
            }

            $result = $response->json();
            $data   = $result['data'] ?? [];

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

            $oracle = DB::connection('oracle');

            // -----------------------------
            // Collect data into arrays once
            // -----------------------------
            $foodsRows = [];
            $foodIds   = [];

            $foodTranslations = [];
            $variationsRows = [];
            $variationOptionsRows = [];
            $partnerVariationOptionsRows = [];

            foreach ($data['foods'] ?? [] as $foodData) {
                $food = $foodData['food'] ?? null;
                if (!$food || !isset($food['id'])) {
                    continue;
                }

                $foodsRows[] = $food;
                $foodIds[]   = (int) $food['id'];

                // Images
                if (!empty($food['image'])) {
                    $this->copyImageFromStorage($food['image'], 'product/');
                }

                foreach ($foodData['translations'] ?? [] as $t) {
                    if (isset($t['id'])) $foodTranslations[] = $t;
                }

                foreach ($foodData['variations'] ?? [] as $v) {
                    if (isset($v['id'])) $variationsRows[] = $v;
                }

                foreach ($foodData['variation_options'] ?? [] as $vo) {
                    if (isset($vo['id'])) $variationOptionsRows[] = $vo;
                }

                foreach ($foodData['partner_variation_options'] ?? [] as $pvo) {
                    if (isset($pvo['id'])) $partnerVariationOptionsRows[] = $pvo;
                }
            }

            $foodIds = array_values(array_unique($foodIds));

            // Addons
            $addonsRows = [];
            $addonIds = [];
            $addonTranslations = [];

            foreach ($data['addons'] ?? [] as $addonData) {
                $addon = $addonData['addon'] ?? null;
                if (!$addon || !isset($addon['id'])) continue;

                $addonsRows[] = $addon;
                $addonIds[]   = (int) $addon['id'];

                foreach ($addonData['translations'] ?? [] as $t) {
                    if (isset($t['id'])) $addonTranslations[] = $t;
                }
            }
            $addonIds = array_values(array_unique($addonIds));

            // Categories
            $categoriesRows = [];
            $categoryIds = [];
            $categoryTranslations = [];

            foreach ($data['categories'] ?? [] as $categoryData) {
                $cat = $categoryData['category'] ?? null;
                if (!$cat || !isset($cat['id'])) continue;

                $categoriesRows[] = $cat;
                $categoryIds[]    = (int) $cat['id'];

                if (!empty($cat['image'])) {
                    $this->copyImageFromStorage($cat['image'], 'category/');
                }

                foreach ($categoryData['translations'] ?? [] as $t) {
                    if (isset($t['id'])) $categoryTranslations[] = $t;
                }
            }
            $categoryIds = array_values(array_unique($categoryIds));

            // Options list
            $optionsRows = [];
            $optionIds = [];
            $optionTranslations = [];

            foreach ($data['options_list'] ?? [] as $optionData) {
                $opt = $optionData['option'] ?? null;
                if (!$opt || !isset($opt['id'])) continue;

                $optionsRows[] = $opt;
                $optionIds[]   = (int) $opt['id'];

                foreach ($optionData['translations'] ?? [] as $t) {
                    if (isset($t['id'])) $optionTranslations[] = $t;
                }
            }
            $optionIds = array_values(array_unique($optionIds));

            // All translations (shared table)
            $allTranslations = array_merge(
                $foodTranslations,
                $addonTranslations,
                $categoryTranslations,
                $optionTranslations
            );

            // -----------------------------
            // One transaction for DB writes
            // -----------------------------
            $oracle->beginTransaction();

            try {
                /**
                 * IMPORTANT:
                 * We only delete subtree for foods that came in this payload.
                 * This is safe even for incremental API.
                 */

                // Delete children by food_id (chunked)
                $this->deleteWhereInChunked($oracle, 'variations', 'food_id', $foodIds);
                $this->deleteWhereInChunked($oracle, 'variation_options', 'food_id', $foodIds);
                $this->deleteWhereInChunked($oracle, 'partner_variation_option', 'food_id', $foodIds);

                // Delete translations for these foods only (type filtered)
                $this->deleteTranslationsByTypeAndIds($oracle, 'App\\Models\\Food', $foodIds);

                // Upsert main tables
                $this->upsertChunked($oracle, 'food', $foodsRows, 'id');
                $this->upsertChunked($oracle, 'add_ons', $addonsRows, 'id');
                $this->upsertChunked($oracle, 'categories', $categoriesRows, 'id');
                $this->upsertChunked($oracle, 'options_list', $optionsRows, 'id');

                // Insert children (faster than updateOrInsert after delete)
                $this->insertChunked($oracle, 'variations', $variationsRows);
                $this->insertChunked($oracle, 'variation_options', $variationOptionsRows);
                $this->insertChunked($oracle, 'partner_variation_option', $partnerVariationOptionsRows);

                // Translations:
                // For addon/category/options we should also delete those types for IDs received to avoid duplicates
                $this->deleteTranslationsByTypeAndIds($oracle, 'App\\Models\\AddOn', $addonIds);
                $this->deleteTranslationsByTypeAndIds($oracle, 'App\\Models\\Category', $categoryIds);
                $this->deleteTranslationsByTypeAndIds($oracle, 'App\\Models\\OptionsList', $optionIds);

                // Then insert fresh translations (or upsert by id)
                // Insert is faster if IDs are unique from source
                $this->insertChunked($oracle, 'translations', $allTranslations);

                $oracle->commit();

            } catch (\Throwable $e) {
                $oracle->rollBack();
                Log::error('SyncFoodJob failed during DB sync', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return;
            }

            Log::info('SyncFoodJob completed successfully', [
                'foods' => count($foodIds),
                'addons' => count($addonIds),
                'categories' => count($categoryIds),
                'options_list' => count($optionIds),
                'partner_variation_options' => count($partnerVariationOptionsRows),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing food', [
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncFoodJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    private function deleteWhereInChunked($oracle, string $table, string $column, array $ids, int $chunkSize = 900): void
    {
        if (empty($ids)) return;

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $oracle->table($table)->whereIn($column, $chunk)->delete();
        }
    }

    private function deleteTranslationsByTypeAndIds($oracle, string $type, array $ids, int $chunkSize = 900): void
    {
        if (empty($ids)) return;

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $oracle->table('translations')
                ->where('translationable_type', $type)
                ->whereIn('translationable_id', $chunk)
                ->delete();
        }
    }

    private function upsertChunked($oracle, string $table, array $rows, string $key, int $chunkSize = 200): void
    {
        if (empty($rows)) return;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            foreach ($chunk as $row) {
                if (!isset($row[$key])) continue;
                $oracle->table($table)->updateOrInsert([$key => $row[$key]], $row);
            }
        }
    }

    private function insertChunked($oracle, string $table, array $rows, int $chunkSize = 500): void
    {
        if (empty($rows)) return;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $oracle->table($table)->insert($chunk);
        }
    }

    private function copyImageFromStorage($filename, $folder = 'product/')
    {
        if (empty($filename)) return;

        $imageSourceBase = rtrim(config('constants.image_source_base'), '/') . '/';
        $relativePath    = $folder . ltrim($filename, '/');

        $destinationPath = public_path('storage/' . $relativePath);

        // ✅ If already exists, skip download (huge speed gain)
        if (file_exists($destinationPath) && filesize($destinationPath) > 0) {
            return;
        }

        try {
            if (!is_dir(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0755, true);
            }

            $sourceUrl = $imageSourceBase . $relativePath;

            $res = Http::timeout(20)->retry(2, 500)->get($sourceUrl);

            if (!$res->successful()) {
                return;
            }

            file_put_contents($destinationPath, $res->body());
        } catch (\Throwable $e) {
            Log::error("Image copy failed for {$relativePath}: " . "Filename: " . $filename . " - " . $e->getMessage());
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
                Log::warning('Invalid timestamp while preparing sync update', [
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
                ->withoutVerifying()
                ->post(config('services.live_server.url') . '/food/update-sync-state', $payload);

            if ($response->successful()) {
                Log::info('Branch sync state updated on live server', [
                    'branch_id' => $branchId,
                    'entities' => array_keys(array_diff_key($payload, ['branch_id' => null])),
                ]);
            } else {
                Log::warning('Failed to update branch sync state on live server', [
                    'branch_id' => $branchId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating branch sync state', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
