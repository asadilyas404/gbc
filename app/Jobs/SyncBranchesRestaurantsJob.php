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

class SyncBranchesRestaurantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncBranchesRestaurantsJob started (API-based)');

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/branches-restaurants/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get branch/restaurant data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received branch/restaurant data from live server', [
                'branches' => count($data['branches'] ?? []),
                'restaurants' => count($data['restaurants'] ?? []),
            ]);

            $syncedBranchIds = [];
            $syncedRestaurantIds = [];

            foreach ($data['branches'] ?? [] as $branch) {
                try {
                    DB::connection('oracle')
                        ->table('tbl_soft_branch')
                        ->updateOrInsert(
                            ['branch_id' => $branch['branch_id']],
                            $branch
                        );

                    $syncedBranchIds[] = $branch['branch_id'];
                    Log::info("Branch ID {$branch['branch_id']} synced successfully.");

                } catch (\Exception $e) {
                    Log::error("Failed syncing branch ID {$branch['branch_id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['restaurants'] ?? [] as $restaurantData) {
                DB::connection('oracle')->beginTransaction();

                try {
                    $restaurant = $restaurantData['restaurant'];

                    DB::connection('oracle')
                        ->table('restaurants')
                        ->updateOrInsert(
                            ['id' => $restaurant['id']],
                            $restaurant
                        );

                    if (!empty($restaurant['logo'])) {
                        $this->copyImageFromStorage($restaurant['logo'], 'restaurant/');
                    }

                    if (!empty($restaurant['cover_photo'])) {
                        $this->copyImageFromStorage($restaurant['cover_photo'], 'restaurant/cover/');
                    }

                    foreach ($restaurantData['translations'] ?? [] as $translation) {
                        DB::connection('oracle')
                            ->table('translations')
                            ->updateOrInsert(
                                ['id' => $translation['id']],
                                $translation
                            );
                    }

                    DB::connection('oracle')->commit();
                    $syncedRestaurantIds[] = $restaurant['id'];
                    Log::info("Restaurant ID {$restaurant['id']} ({$restaurant['name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing restaurant ID {$restaurantData['restaurant']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!empty($syncedBranchIds) || !empty($syncedRestaurantIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->post(config('services.live_server.url') . '/branches-restaurants/mark-pushed', [
                            'branch_ids' => $syncedBranchIds,
                            'restaurant_ids' => $syncedRestaurantIds,
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

            Log::info('SyncBranchesRestaurantsJob completed successfully', [
                'synced_branches' => count($syncedBranchIds),
                'synced_restaurants' => count($syncedRestaurantIds),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing branches/restaurants', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncBranchesRestaurantsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function copyImageFromStorage($filename, $folder = 'restaurant/')
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
                Log::warning("Image not found or failed to fetch: {$sourceUrl}");
                return;
            }

            file_put_contents($destinationPath, $imageData);
            Log::info("Image copied to: " . $destinationPath);

        } catch (\Exception $e) {
            Log::error("Image copy failed for {$relativePath}: " . $e->getMessage());
        }
    }
}
