<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        \Log::info('SyncEmployeesJob started');
        try {

            // Sync EMPLOYEE ROLES
            $employeesRoles = DB::connection('oracle_live')
                ->table('employee_roles')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($employeesRoles as $role) {
                try {
                    DB::connection('oracle')
                        ->table('employee_roles')
                        ->updateOrInsert(
                            ['id' => $role->id],
                            (array) $role
                        );

                    DB::connection('oracle_live')
                        ->table('employee_roles')
                        ->where('id', $role->id)
                        ->update(['is_pushed' => 'Y']);

                    // Log::info("Employee ID {$employee->id} synced successfully.");
                } catch (\Exception $e) {
                    Log::error("Failed syncing role ID {$role->id}: " . $e->getMessage());
                }
            }

            // Sync EMPLOYEES
            $employees = DB::connection('oracle_live')
                ->table('vendor_employees')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($employees as $employee) {
                try {
                    DB::connection('oracle')
                        ->table('vendor_employees')
                        ->updateOrInsert(
                            ['id' => $employee->id],
                            (array) $employee
                        );

                    DB::connection('oracle_live')
                        ->table('vendor_employees')
                        ->where('id', $employee->id)
                        ->update(['is_pushed' => 'Y']);

                    // Log::info("Employee ID {$employee->id} synced successfully.");
                } catch (\Exception $e) {
                    Log::error("Failed syncing employee ID {$employee->id}: " . $e->getMessage());
                }
            }

            \Log::info('SyncEmployeesJob completed successfully.');
        } catch (\Exception $e) {
            Log::error("SyncEmployeesJob failed: " . $e->getMessage());
        }
    }
}
