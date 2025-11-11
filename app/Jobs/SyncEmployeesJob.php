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

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncEmployeesJob started (API-based)');

        try {
            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withoutVerifying() // disables SSL certificate verification
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/employees-users/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get employee/user data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received employee/user data from live server', [
                'employee_roles' => count($data['employee_roles'] ?? []),
                'employees' => count($data['employees'] ?? []),
                'users' => count($data['users'] ?? []),
            ]);

            $syncedEmployeeRoleIds = [];
            $syncedEmployeeIds = [];
            $syncedUserIds = [];

            foreach ($data['employee_roles'] ?? [] as $role) {
                try {
                    DB::connection('oracle')
                        ->table('employee_roles')
                        ->updateOrInsert(
                            ['id' => $role['id']],
                            $role
                        );

                    $syncedEmployeeRoleIds[] = $role['id'];

                } catch (\Exception $e) {
                    Log::error("Failed syncing role ID {$role['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            foreach ($data['employees'] ?? [] as $employee) {
                try {
                    DB::connection('oracle')
                        ->table('vendor_employees')
                        ->updateOrInsert(
                            ['id' => $employee['id']],
                            $employee
                        );

                    $syncedEmployeeIds[] = $employee['id'];

                } catch (\Exception $e) {
                    Log::error("Failed syncing employee ID {$employee['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

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

            if (!empty($syncedEmployeeRoleIds) || !empty($syncedEmployeeIds) || !empty($syncedUserIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->withoutVerifying() // disables SSL certificate verification
                        ->post(config('services.live_server.url') . '/employees-users/mark-pushed', [
                            'employee_role_ids' => $syncedEmployeeRoleIds,
                            'employee_ids' => $syncedEmployeeIds,
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

            Log::info('SyncEmployeesJob completed successfully', [
                'synced_employee_roles' => count($syncedEmployeeRoleIds),
                'synced_employees' => count($syncedEmployeeIds),
                'synced_users' => count($syncedUserIds),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing employees/users', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncEmployeesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function copyImageFromStorage($filename, $folder = 'images/')
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
