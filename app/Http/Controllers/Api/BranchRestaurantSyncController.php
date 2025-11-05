<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchRestaurantSyncController extends Controller
{
    /**
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData()
    {
        try {
            $data = [
                'branches' => [],
                'restaurants' => [],
            ];

            $branches = DB::connection('oracle')
                ->table('tbl_soft_branch')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($branches as $branch) {
                $data['branches'][] = (array) $branch;
            }

            $restaurants = DB::connection('oracle')
                ->table('restaurants')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($restaurants as $restaurant) {
                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $restaurant->id)
                    ->where('translationable_type', 'App\\Models\\Restaurant')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['restaurants'][] = [
                    'restaurant' => (array) $restaurant,
                    'translations' => $translations,
                ];
            }

            Log::info('Branch and restaurant data retrieved for sync', [
                'branches_count' => count($data['branches']),
                'restaurants_count' => count($data['restaurants']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'branches' => count($data['branches']),
                    'restaurants' => count($data['restaurants']),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get branch/restaurant data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branch/restaurant data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsPushed(Request $request)
    {
        try {
            $branchIds = $request->input('branch_ids', []);
            $restaurantIds = $request->input('restaurant_ids', []);

            DB::connection('oracle')->beginTransaction();

            if (!empty($branchIds)) {
                DB::connection('oracle')
                    ->table('tbl_soft_branch')
                    ->whereIn('branch_id', $branchIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($restaurantIds)) {
                DB::connection('oracle')
                    ->table('restaurants')
                    ->whereIn('id', $restaurantIds)
                    ->update(['is_pushed' => 'Y']);
            }

            DB::connection('oracle')->commit();

            Log::info('Branches and restaurants marked as pushed', [
                'branch_count' => count($branchIds),
                'restaurant_count' => count($restaurantIds),
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

