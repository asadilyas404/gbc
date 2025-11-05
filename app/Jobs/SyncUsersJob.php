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

class SyncUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        Log::info('SyncUsersJob started (API-based)');

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/employees-users/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get user data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received user data from live server', [
                'users' => count($data['users'] ?? []),
            ]);

            $syncedUserIds = [];

            foreach ($data['users'] ?? [] as $user) {
                DB::connection('oracle')->beginTransaction();

                try {
                    DB::connection('oracle')
                        ->table('users')
                        ->updateOrInsert(
                            ['id' => $user['id']],
                            $user
                        );

                    if (!empty($user['image_url'])) {
                        $this->copyImageFromStorage($user['image_url'], 'images/');
                    }

                    if (!empty($user['degital_signature_url'])) {
                        $this->copyImageFromStorage($user['degital_signature_url'], 'images/');
                    }

                    DB::connection('oracle')->commit();
                    $syncedUserIds[] = $user['id'];
                    Log::info("User ID {$user['id']} ({$user['name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing user ID {$user['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!empty($syncedUserIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->post(config('services.live_server.url') . '/employees-users/mark-pushed', [
                            'employee_role_ids' => [],
                            'employee_ids' => [],
                            'user_ids' => $syncedUserIds,
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

            Log::info('SyncUsersJob completed successfully', [
                'synced_users' => count($syncedUserIds),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing users', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncUsersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function copyImageFromStorage(string $filename, string $folder = 'images/'): void
    {
        $imageSourceBase = config('constants.image_source_base');
        $imageSourceBase = rtrim($imageSourceBase, '/') . '/';
        $relativePath = $folder . $filename;

        $sourceUrl = $imageSourceBase . $relativePath;
        $destinationPath = public_path($relativePath);

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
