<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderSyncController extends Controller
{
    /**
     * Sync multiple orders in bulk (all orders in one request)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array',
            'orders.*.order' => 'required|array',
            'orders.*.order.id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $orders = $request->input('orders');
        $syncedCount = 0;
        $failedCount = 0;
        $orderIds = [];

        DB::connection('oracle')->beginTransaction();

        try {
            foreach ($orders as $data) {
                try {
                    $orderIds[] = $data['order']['id'];

                    DB::connection('oracle')
                        ->table('orders')
                        ->updateOrInsert(
                            ['id' => $data['order']['id']],
                            $data['order']
                        );

                    if (!empty($data['order_details'])) {
                        foreach ($data['order_details'] as $detail) {
                            DB::connection('oracle')
                                ->table('order_details')
                                ->updateOrInsert(
                                    ['id' => $detail['id']],
                                    $detail
                                );
                        }
                    }

                    if (!empty($data['additional_details'])) {
                        foreach ($data['additional_details'] as $detail) {
                            DB::connection('oracle')
                                ->table('pos_order_additional_dtl')
                                ->updateOrInsert(
                                    ['id' => $detail['id']],
                                    $detail
                                );
                        }
                    }

                    if (!empty($data['kitchen_status'])) {
                        foreach ($data['kitchen_status'] as $status) {
                            $exists = DB::connection('oracle')
                                ->table('kitchen_order_status_logs')
                                ->where('id', $status['id'])
                                ->exists();

                            if (!$exists) {
                                DB::connection('oracle')
                                    ->table('kitchen_order_status_logs')
                                    ->insert($status);
                            }
                        }
                    }

                    $syncedCount++;

                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Failed syncing individual order in bulk", [
                        'order_id' => $data['order']['id'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::connection('oracle')->commit();

            Log::info("Bulk orders synced successfully via API", [
                'total' => count($orders),
                'synced' => $syncedCount,
                'failed' => $failedCount,
                'order_ids' => $orderIds
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Orders synced successfully',
                'total' => count($orders),
                'synced' => $syncedCount,
                'failed' => $failedCount,
                'order_ids' => $orderIds
            ], 200);

        } catch (\Exception $e) {
            DB::connection('oracle')->rollBack();

            Log::error("Failed syncing orders bulk via API", [
                'total_orders' => count($orders),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync complete order data (single order - kept for backward compatibility)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncComplete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order' => 'required|array',
            'order.id' => 'required',
            'order_details' => 'nullable|array',
            'additional_details' => 'nullable|array',
            'kitchen_status' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        DB::connection('oracle')->beginTransaction();

        try {
            DB::connection('oracle')
                ->table('orders')
                ->updateOrInsert(
                    ['id' => $data['order']['id']],
                    $data['order']
                );

            if (!empty($data['order_details'])) {
                foreach ($data['order_details'] as $detail) {
                    DB::connection('oracle')
                        ->table('order_details')
                        ->updateOrInsert(
                            ['id' => $detail['id']],
                            $detail
                        );
                }
            }

            if (!empty($data['additional_details'])) {
                foreach ($data['additional_details'] as $detail) {
                    DB::connection('oracle')
                        ->table('pos_order_additional_dtl')
                        ->updateOrInsert(
                            ['id' => $detail['id']],
                            $detail
                        );
                }
            }

            if (!empty($data['kitchen_status'])) {
                foreach ($data['kitchen_status'] as $status) {
                    $exists = DB::connection('oracle')
                        ->table('kitchen_order_status_logs')
                        ->where('id', $status['id'])
                        ->exists();

                    if (!$exists) {
                        DB::connection('oracle')
                            ->table('kitchen_order_status_logs')
                            ->insert($status);
                    }
                }
            }

            DB::connection('oracle')->commit();

            Log::info("Order synced successfully via API", [
                'order_id' => $data['order']['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order synced successfully',
                'order_id' => $data['order']['id']
            ], 200);

        } catch (\Exception $e) {
            DB::connection('oracle')->rollBack();

            Log::error("Failed syncing order via API", [
                'order_id' => $data['order']['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
