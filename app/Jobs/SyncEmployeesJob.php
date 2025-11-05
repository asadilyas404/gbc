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
                ->withToken(config('services.live_server.token'))
                ->retry(3, 1000)
                ->get(config('services.live_server.url') . '/employees-users/get-data');

            if (!$response->successful()) {
                Log::error('Failed to get employee data from live server', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            Log::info('Received employee data from live server', [
                'employee_roles' => count($data['employee_roles'] ?? []),
                'employees' => count($data['employees'] ?? []),
            ]);

            $syncedEmployeeRoleIds = [];
            $syncedEmployeeIds = [];

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

            if (!empty($syncedEmployeeRoleIds) || !empty($syncedEmployeeIds)) {
                try {
                    $markResponse = Http::timeout(30)
                        ->withToken(config('services.live_server.token'))
                        ->post(config('services.live_server.url') . '/employees-users/mark-pushed', [
                            'employee_role_ids' => $syncedEmployeeRoleIds,
                            'employee_ids' => $syncedEmployeeIds,
                            'user_ids' => [],
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
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Connection error while syncing employees', [
                'error' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('SyncEmployeesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
