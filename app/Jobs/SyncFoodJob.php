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

class SyncFoodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncFoodJob started (API-based)');

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying() // disables SSL certificate verification
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/food/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get food data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received food data from live server', [
                'foods' => count($data['foods'] ?? []),
                'addons' => count($data['addons'] ?? []),
                'categories' => count($data['categories'] ?? []),
                'options_list' => count($data['options_list'] ?? []),
            ]);

            $syncedFoodIds = [];
            $syncedAddonIds = [];
            $syncedCategoryIds = [];
            $syncedOptionsListIds = [];

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

                    DB::connection('oracle')->commit();
                    $syncedFoodIds[] = $food['id'];

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
                        ->withoutVerifying() // disables SSL certificate verification
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
}
