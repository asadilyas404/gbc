<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerSyncController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData()
    {
        try {
            $data = [
                'customers' => [],
                'order_partners' => [],
            ];

            $customers = DB::connection('oracle')
                ->table('tbl_sale_customer')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($customers as $customer) {
                $data['customers'][] = (array) $customer;
            }

            $orderPartners = DB::connection('oracle')
                ->table('tbl_sale_order_partners')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($orderPartners as $orderPartner) {
                $data['order_partners'][] = (array) $orderPartner;
            }

            Log::info('Customer data retrieved for sync', [
                'customers_count' => count($data['customers']),
                'order_partners_count' => count($data['order_partners']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'counts' => [
                    'customers' => count($data['customers']),
                    'order_partners' => count($data['order_partners']),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get customer data for sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**s
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsPushed(Request $request)
    {
        try {
            $customerIds = $request->input('customer_ids', []);
            $orderPartnerIds = $request->input('order_partner_ids', []);

            DB::connection('oracle')->beginTransaction();

            if (!empty($customerIds)) {
                DB::connection('oracle')
                    ->table('tbl_sale_customer')
                    ->whereIn('customer_id', $customerIds)
                    ->update(['is_pushed' => 'Y']);
            }

            if (!empty($orderPartnerIds)) {
                DB::connection('oracle')
                    ->table('tbl_sale_order_partners')
                    ->whereIn('partner_id', $orderPartnerIds)
                    ->update(['is_pushed' => 'Y']);
            }

            DB::connection('oracle')->commit();

            Log::info('Customers marked as pushed', [
                'customer_count' => count($customerIds),
                'order_partner_count' => count($orderPartnerIds),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customers marked as pushed successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::connection('oracle')->rollBack();

            Log::error('Failed to mark customers as pushed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark customers as pushed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
