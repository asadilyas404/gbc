<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DataPushController extends Controller
{
    /**
     * Filter array keys based on the columns of the specified table.
     */
    private function filterByTableColumns($connection, $table, array $data)
    {
        try {
            $columns = $connection->getSchemaBuilder()->getColumnListing($table);
            return array_filter($data, fn($key) => in_array($key, $columns), ARRAY_FILTER_USE_KEY);
        } catch (Exception $e) {
            Log::error("Column filtering failed for table [$table]: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Pushes orders and related data from source DB to target DB.
     */
    public function pushInvoices()
    {
        try {
            $source = DB::connection('oracle_source');
            $target = DB::connection('oracle_target');

            $orders = $source->table('orders')
                ->where('is_pushed', 'N')
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['status' => 'success', 'message' => 'No new orders to push.']);
            }

            foreach ($orders as $order) {
                $exists = $target->table('orders')->where('id', $order->id)->exists();

                if ($exists) {
                    continue; // Skip if order already exists
                }

                // Prepare and insert order
                $orderData = $this->filterByTableColumns($target, 'orders', (array) $order);
                unset($orderData['is_pushed'], $orderData['table_id']);

                $target->table('orders')->insert($orderData);

                // Insert related order_details
                $details = $source->table('order_details')
                    ->where('order_id', $order->id)
                    ->get();

                foreach ($details as $detail) {
                    $detailData = $this->filterByTableColumns($target, 'order_details', (array) $detail);
                    $target->table('order_details')->insert($detailData);
                }
                            dd($detail);


                // Insert related pos_order_additional_dtl
                $additionalDetails = $source->table('pos_order_additional_dtl')
                    ->where('order_id', $order->id)
                    ->get();

                foreach ($additionalDetails as $addDetail) {
                    $addDetailData = $this->filterByTableColumns($target, 'pos_order_additional_dtl', (array) $addDetail);
                    $target->table('pos_order_additional_dtl')->insert($addDetailData);
                }

                // Mark order as pushed
                $source->table('orders')
                    ->where('id', $order->id)
                    ->update(['is_pushed' => 'Y']);
            }

            return response()->json(['status' => 'success', 'message' => 'Orders pushed successfully.']);
        } catch (Exception $e) {
            Log::error('Data push failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Push failed: ' . $e->getMessage()], 500);
        }
    }
}
