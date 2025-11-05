<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeUserSyncController extends Controller
{
    /**
     * Get unpushed employee roles, employees, and users data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData()
    {
        try {
            $data = [
                'employee_roles' => [],
                'employees' => [],
                'users' => [],
            ];

            // Get unpushed EMPLOYEE ROLES
            $employeeRoles = DB::connection('oracle')
                ->table('employee_roles')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($employeeRoles as $role) {
                $data['employee_roles'][] = (array) $role;
            }

            // Get unpushed EMPLOYEES
            $employees = DB::connection('oracle')
                ->table('vendor_employees')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($employees as $employee) {
                $data['employees'][] = (array) $employee;
            }

            // Get unpushed USERS
            $users = DB::connection('oracle')
                ->table('users')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($users as $user) {
                $data['users'][] = (array) $user;
            }

            Log::info('Employee and user data retrieved for sync', [
                'employee_roles_count' => count($data['employee_roles']),
                'employees_count' => count($data['employees']),
                'users_count' => count($data['users']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'employee_roles' => count($data['employee_roles']),
                    'employees' => count($data['employees']),
                    'users' => count($data['users']),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get employee/user data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee/user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark employee roles, employees, and users as pushed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsPushed(Request $request)
    {
        try {
            $employeeRoleIds = $request->input('employee_role_ids', []);
            $employeeIds = $request->input('employee_ids', []);
            $userIds = $request->input('user_ids', []);

            DB::connection('oracle')->beginTransaction();

            if (!empty($employeeRoleIds)) {
                DB::connection('oracle')
                    ->table('employee_roles')
                    ->whereIn('id', $employeeRoleIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($employeeIds)) {
                DB::connection('oracle')
                    ->table('vendor_employees')
                    ->whereIn('id', $employeeIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($userIds)) {
                DB::connection('oracle')
                    ->table('users')
                    ->whereIn('id', $userIds)
                    ->update(['is_pushed' => 'Y']);
            }

            DB::connection('oracle')->commit();

            Log::info('Employee and user items marked as pushed', [
                'employee_role_count' => count($employeeRoleIds),
                'employee_count' => count($employeeIds),
                'user_count' => count($userIds),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Items marked as pushed successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::connection('oracle')->rollBack();

            Log::error('Failed to mark items as pushed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark items as pushed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

