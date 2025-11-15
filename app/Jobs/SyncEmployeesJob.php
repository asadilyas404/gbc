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

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SYNC_ENTITY_TYPES = [
        'employee_roles',
        'employees',
        'users',
    ];

    public function handle()
    {
        set_time_limit(300);
        Log::info('SyncEmployeesJob started (API-based)');

        try {
            $branchId = Helpers::get_restaurant_id();

            if (empty($branchId)) {
                Log::warning('SyncEmployeesJob halted: branch/restaurant context missing');
                return;
            }

            $response = Http::timeout(config('services.live_server.timeout', 60))
                ->withoutVerifying() // disables SSL certificate verification
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/employees-users/get-data', [
                    'branch_id' => $branchId,
                ]);

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
            $latestSyncedTimestamps = array_fill_keys(self::SYNC_ENTITY_TYPES, null);

            foreach ($data['employee_roles'] ?? [] as $role) {
                try {
                    DB::connection('oracle')
                        ->table('employee_roles')
                        ->updateOrInsert(
                            ['id' => $role['id']],
                            $role
                        );

                    $syncedEmployeeRoleIds[] = $role['id'];
                    $latestSyncedTimestamps['employee_roles'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['employee_roles'],
                        $role['updated_at'] ?? null
                    );

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
                    $latestSyncedTimestamps['employees'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['employees'],
                        $employee['updated_at'] ?? null
                    );

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
                    $latestSyncedTimestamps['users'] = $this->pickLatestTimestamp(
                        $latestSyncedTimestamps['users'],
                        $user['updated_at'] ?? null
                    );
                    Log::info("User ID {$user['id']} ({$user['name']}) synced successfully.");

                } catch (\Exception $e) {
                    DB::connection('oracle')->rollBack();
                    Log::error("Failed syncing user ID {$user['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->sendSyncStateUpdate((int) $branchId, $latestSyncedTimestamps);

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

    private function pickLatestTimestamp(?string $current, ?string $candidate): ?string
    {
        if (empty($candidate)) {
            return $current;
        }

        try {
            $candidateCarbon = Carbon::parse($candidate);
        } catch (\Exception $e) {
            Log::warning('Invalid candidate timestamp received during employee sync', [
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
            Log::warning('Invalid stored timestamp during employee sync, overriding', [
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
                Log::warning('Invalid timestamp while preparing employee sync update', [
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
                ->post(config('services.live_server.url') . '/employees-users/update-sync-state', $payload);

            if ($response->successful()) {
                Log::info('Branch sync state updated on live server (employees/users)', [
                    'branch_id' => $branchId,
                    'entities' => array_keys(array_diff_key($payload, ['branch_id' => null])),
                ]);
            } else {
                Log::warning('Failed to update branch sync state on live server (employees/users)', [
                    'branch_id' => $branchId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating branch sync state (employees/users)', [
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
