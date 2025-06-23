<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncFoodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        \Log::info('SyncFoodJob started');
        try {
            // Sync FOOD
            $foods = DB::connection('oracle_live')
                ->table('food')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($foods as $food) {
                DB::connection('oracle')->beginTransaction();

                try {
                    // Sync food record
                    DB::connection('oracle')
                        ->table('food')
                        ->updateOrInsert(
                            ['id' => $food->id],
                            (array) $food
                        );

                    // Sync variations
                    $variations = DB::connection('oracle_live')
                        ->table('variations')
                        ->where('food_id', $food->id)
                        ->get();

                    foreach ($variations as $variation) {
                        DB::connection('oracle')
                            ->table('variations')
                            ->updateOrInsert(
                                ['id' => $variation->id],
                                (array) $variation
                            );
                    }

                    // Sync variation options
                    $variationOptions = DB::connection('oracle_live')
                        ->table('variation_options')
                        ->where('food_id', $food->id)
                        ->get();

                    foreach ($variationOptions as $option) {
                        DB::connection('oracle')
                            ->table('variation_options')
                            ->updateOrInsert(
                                ['id' => $option->id],
                                (array) $option
                            );
                    }

                    // Mark food as pushed
                    DB::connection('oracle_live')
                        ->table('food')
                        ->where('id', $food->id)
                        ->update(['is_pushed' => 'Y']);

                    DB::connection('oracle')->commit();
                    // Log::info("Food ID {$food->id} synced successfully.");
                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing food ID {$food->id}: " . $e->getMessage());
                }
            }

            // Sync ADD_ONS
            $addons = DB::connection('oracle_live')
                ->table('add_ons')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($addons as $addon) {
                try {
                    DB::connection('oracle')
                        ->table('add_ons')
                        ->updateOrInsert(
                            ['id' => $addon->id],
                            (array) $addon
                        );

                    DB::connection('oracle_live')
                        ->table('add_ons')
                        ->where('id', $addon->id)
                        ->update(['is_pushed' => 'Y']);

                    // Log::info("AddOn ID {$addon->id} synced successfully.");
                } catch (\Exception $e) {
                    Log::error("Failed syncing AddOn ID {$addon->id}: " . $e->getMessage());
                }
            }

            // Sync CATEGORIES
            $categories = DB::connection('oracle_live')
                ->table('categories')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($categories as $category) {
                try {
                    DB::connection('oracle')
                        ->table('categories')
                        ->updateOrInsert(
                            ['id' => $category->id],
                            (array) $category
                        );

                    DB::connection('oracle_live')
                        ->table('categories')
                        ->where('id', $category->id)
                        ->update(['is_pushed' => 'Y']);

                    // Log::info("Category ID {$category->id} synced successfully.");
                } catch (\Exception $e) {
                    Log::error("Failed syncing Category ID {$category->id}: " . $e->getMessage());
                }
            }

            \Log::info('SyncFoodJob completed successfully.');
        } catch (\Exception $e) {
            Log::error("SyncFoodJob failed: " . $e->getMessage());
        }
    }
}
