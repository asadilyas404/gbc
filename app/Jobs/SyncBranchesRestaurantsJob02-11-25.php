<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBranchesRestaurantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        Log::info('SyncBranchesRestaurantsJob started');
        try {
            $branches = DB::connection('oracle_live')
                ->table('tbl_soft_branch')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($branches as $branch) {
                try {
                    DB::connection('oracle')
                        ->table('tbl_soft_branch')
                        ->updateOrInsert(
                            ['branch_id' => $branch->branch_id],
                            (array) $branch
                        );

                    DB::connection('oracle_live')
                        ->table('tbl_soft_branch')
                        ->where('branch_id', $branch->branch_id)
                        ->update(['is_pushed' => 'Y']);

                    Log::info("Branch ID {$branch->branch_id} synced successfully.");
                } catch (\Exception $e) {
                    Log::error("Failed syncing branch ID {$branch->branch_id}: " . $e->getMessage());
                }
            }

            $restaurants = DB::connection('oracle_live')
                ->table('restaurants')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($restaurants as $restaurant) {
                DB::connection('oracle')->beginTransaction();

                try {
                    DB::connection('oracle')
                        ->table('restaurants')
                        ->updateOrInsert(
                            ['id' => $restaurant->id],
                            (array) $restaurant
                        );

                    if (!empty($restaurant->logo)) {
                        $this->copyImageFromStorage($restaurant->logo, 'restaurant/');
                    }

                    if (!empty($restaurant->cover_photo)) {
                        $this->copyImageFromStorage($restaurant->cover_photo, 'restaurant/cover/');
                    }

                    $this->syncTranslations('oracle_live', 'App\\Models\\Restaurant', $restaurant->id);

                    DB::connection('oracle_live')
                        ->table('restaurants')
                        ->where('id', $restaurant->id)
                        ->update(['is_pushed' => 'Y']);

                    DB::connection('oracle')->commit();
                    Log::info("Restaurant ID {$restaurant->id} ({$restaurant->name}) synced successfully.");
                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing restaurant ID {$restaurant->id}: " . $e->getMessage());
                }
            }

            Log::info('SyncBranchesRestaurantsJob completed successfully.');
        } catch (\Exception $e) {
            Log::error("SyncBranchesRestaurantsJob failed: " . $e->getMessage());
        }
    }

    /**
     * Sync translations for a specific model.
     *
     * @param string $sourceConnection
     * @param string $modelType (e.g., App\Models\Restaurant)
     * @param int $modelId
     * @return void
     */
    private function syncTranslations(string $sourceConnection, string $modelType, int $modelId): void
    {
        try {
            $translations = DB::connection($sourceConnection)
                ->table('translations')
                ->where('translationable_id', $modelId)
                ->where('translationable_type', $modelType)
                ->get();

            foreach ($translations as $translation) {
                DB::connection('oracle')
                    ->table('translations')
                    ->updateOrInsert(
                        ['id' => $translation->id],
                        (array) $translation
                    );
            }
        } catch (\Exception $e) {
            Log::error("Failed syncing translations for {$modelType} ID {$modelId}: " . $e->getMessage());
        }
    }

    /**
     * Copy image from remote storage to local public path.
     *
     * @param string $filename
     * @param string $folder
     * @return void
     */
    private function copyImageFromStorage(string $filename, string $folder = 'restaurant/'): void
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

