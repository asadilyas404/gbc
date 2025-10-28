<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        Log::info('SyncUsersJob started');
        try {
            $users = DB::connection('oracle_live')
                ->table('users')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($users as $user) {
                DB::connection('oracle')->beginTransaction();

                try {
                    DB::connection('oracle')
                        ->table('users')
                        ->updateOrInsert(
                            ['id' => $user->id],
                            (array) $user
                        );

                    if (!empty($user->image_url)) {
                        $this->copyImageFromStorage($user->image_url, 'images/');
                    }

                    if (!empty($user->degital_signature_url)) {
                        $this->copyImageFromStorage($user->degital_signature_url, 'images/');
                    }

                    // Sync tbl_soft_user_branch
                    // $userBranches = DB::connection('oracle_live')
                    //     ->table('tbl_soft_user_branch')
                    //     ->where('user_id', $user->id)
                    //     ->get();

                    // DB::connection('oracle')
                    //     ->table('tbl_soft_user_branch')
                    //     ->where('user_id', $user->id)
                    //     ->delete();

                    // foreach ($userBranches as $branch) {
                    //     DB::connection('oracle')
                    //         ->table('tbl_soft_user_branch')
                    //         ->insert((array) $branch);
                    // }

                    DB::connection('oracle_live')
                        ->table('users')
                        ->where('id', $user->id)
                        ->update(['is_pushed' => 'Y']);

                    DB::connection('oracle')->commit();
                    Log::info("User ID {$user->id} ({$user->name}) synced successfully.");
                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing user ID {$user->id}: " . $e->getMessage());
                }
            }

            Log::info('SyncUsersJob completed successfully.');
        } catch (\Exception $e) {
            Log::error("SyncUsersJob failed: " . $e->getMessage());
        }
    }

    /**
     * Copy image from remote storage to local public path.
     *
     * @param string $filename
     * @param string $folder
     * @return void
     */
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

