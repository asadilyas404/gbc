<?php

namespace App\Jobs;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
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

    private const SYNC_ENTITY_TYPES = [
        'branches',
        'restaurants',
        'partners',
    ];

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncBranchesRestaurantsJob started (API-based)');
        
        try {
            $branchId = Helpers::get_restaurant_id();
            
            if (empty($branchId)) {
                Log::warning('SyncBranchesRestaurantsJob halted: branch/restaurant context missing');
                return;
            }
            
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->withoutVerifying() // disables SSL certificate verification
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/branches-restaurants/get-data', [
                    'branch_id' => $branchId,
                ]);

            if (!$response->successful()) {
                Log::error('Failed to get branch/restaurant data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            dd($data);
            Log::info('Received branch/restaurant data from live server', [
                'branches' => count($data['branches'] ?? []),
                'restaurants' => count($data['restaurants'] ?? []),
            ]);

            $syncedBranchIds = [];
            $syncedRestaurantIds = [];
            $syncPartnerIds = [];
            $latestSyncedTimestamps = array_fill_keys(self::SYNC_ENTITY_TYPES, null);

            foreach ($data['branches'] ?? [] as $branch) {
                try {
                    DB::connection('oracle')
                        ->table('tbl_soft_branch')
                        ->updateOrInsert(
                            ['branch_id' => $branch['branch_id']],
                            $branch
                        );

                    $syncedBranchIds[] = $branch['branch_id'];
                    $latestSyncedTimestamps['branches'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['branches'],
                        $branch['updated_at'] ?? null
                    );
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
                    $latestSyncedTimestamps['restaurants'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['restaurants'],
                        $restaurant['updated_at'] ?? null
                    );
                    Log::info("Restaurant ID {$restaurant['id']} ({$restaurant['name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing restaurant ID {$restaurantData['restaurant']['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['partners'] ?? [] as $partner) {
                try {
                    DB::connection('oracle')
                        ->table('tbl_sale_order_partners')
                        ->updateOrInsert(
                            ['id' => $partner['id']],
                            $partner
                        );

                    $syncPartnerIds[] = $partner['id'];
                    $latestSyncedTimestamps['partners'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['partners'],
                        $partner['updated_at'] ?? null
                    );
                    Log::info("Partner ID {$partner['id']} synced successfully.");

                } catch (\Exception $e) {
                    Log::error("Failed syncing partner ID {$partner['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->sendSyncStateUpdate((int) $branchId, $latestSyncedTimestamps);

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

    private function pickLatestTimestamp(?string $current, ?string $candidate): ?string
    {
        if (empty($candidate)) {
            return $current;
        }

        try {
            $candidateCarbon = Carbon::parse($candidate);
        } catch (\Exception $e) {
            Log::warning('Invalid candidate timestamp received during branch sync', [
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
            Log::warning('Invalid stored timestamp during branch sync, overriding', [
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
                Log::warning('Invalid timestamp while preparing branch sync update', [
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
                ->post(config('services.live_server.url') . '/branches-restaurants/update-sync-state', $payload);

            if ($response->successful()) {
                Log::info('Branch sync state updated on live server (branches/restaurants)', [
                    'branch_id' => $branchId,
                    'entities' => array_keys(array_diff_key($payload, ['branch_id' => null])),
                ]);
            } else {
                Log::warning('Failed to update branch sync state on live server (branches/restaurants)', [
                    'branch_id' => $branchId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating branch sync state (branches/restaurants)', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
