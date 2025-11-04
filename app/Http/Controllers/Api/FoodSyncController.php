<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FoodSyncController extends Controller
{
    /**
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFoodData()
    {
        try {
            $data = [
                'foods' => [],
                'addons' => [],
                'categories' => [],
                'options_list' => [],
            ];

            $foods = DB::connection('oracle')
                ->table('food')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($foods as $food) {
                $variations = DB::connection('oracle')
                    ->table('variations')
                    ->where('food_id', $food->id)
                    ->get()
                    ->map(function ($v) {
                        return (array) $v;
                    })
                    ->toArray();

                $variationOptions = DB::connection('oracle')
                    ->table('variation_options')
                    ->where('food_id', $food->id)
                    ->get()
                    ->map(function ($vo) {
                        return (array) $vo;
                    })
                    ->toArray();

                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $food->id)
                    ->where('translationable_type', 'App\\Models\\Food')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['foods'][] = [
                    'food' => (array) $food,
                    'variations' => $variations,
                    'variation_options' => $variationOptions,
                    'translations' => $translations,
                ];
            }

            $addons = DB::connection('oracle')
                ->table('add_ons')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($addons as $addon) {
                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $addon->id)
                    ->where('translationable_type', 'App\\Models\\AddOn')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['addons'][] = [
                    'addon' => (array) $addon,
                    'translations' => $translations,
                ];
            }

            $categories = DB::connection('oracle')
                ->table('categories')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($categories as $category) {
                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $category->id)
                    ->where('translationable_type', 'App\\Models\\Category')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['categories'][] = [
                    'category' => (array) $category,
                    'translations' => $translations,
                ];
            }

            $optionsList = DB::connection('oracle')
                ->table('options_list')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($optionsList as $option) {
                $translations = DB::connection('oracle')
                    ->table('translations')
                    ->where('translationable_id', $option->id)
                    ->where('translationable_type', 'App\\Models\\OptionsList')
                    ->get()
                    ->map(function ($t) {
                        return (array) $t;
                    })
                    ->toArray();

                $data['options_list'][] = [
                    'option' => (array) $option,
                    'translations' => $translations,
                ];
            }

            Log::info('Food data retrieved for sync', [
                'foods_count' => count($data['foods']),
                'addons_count' => count($data['addons']),
                'categories_count' => count($data['categories']),
                'options_list_count' => count($data['options_list']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'foods' => count($data['foods']),
                    'addons' => count($data['addons']),
                    'categories' => count($data['categories']),
                    'options_list' => count($data['options_list']),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get food data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve food data',
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
            $foodIds = $request->input('food_ids', []);
            $addonIds = $request->input('addon_ids', []);
            $categoryIds = $request->input('category_ids', []);
            $optionsListIds = $request->input('options_list_ids', []);

            DB::connection('oracle')->beginTransaction();

            if (!empty($foodIds)) {
                DB::connection('oracle')
                    ->table('food')
                    ->whereIn('id', $foodIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($addonIds)) {
                DB::connection('oracle')
                    ->table('add_ons')
                    ->whereIn('id', $addonIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($categoryIds)) {
                DB::connection('oracle')
                    ->table('categories')
                    ->whereIn('id', $categoryIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($optionsListIds)) {
                DB::connection('oracle')
                    ->table('options_list')
                    ->whereIn('id', $optionsListIds)
                    ->update(['is_pushed' => 'Y']);
            }

            DB::connection('oracle')->commit();

            Log::info('Food items marked as pushed', [
                'food_count' => count($foodIds),
                'addon_count' => count($addonIds),
                'category_count' => count($categoryIds),
                'options_list_count' => count($optionsListIds),
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

