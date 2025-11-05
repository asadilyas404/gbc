<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        set_time_limit(300);
        Log::info('SyncCustomersJob started');
        try {
            $customers = DB::connection('oracle')
                ->table('tbl_sale_customer')
                ->where('is_pushed', '!=', 'Y')
                ->orWhereNull('is_pushed')
                ->get();

            foreach ($customers as $customer) {
                DB::connection('oracle_live')->beginTransaction();

                try {
                    DB::connection('oracle_live')
                        ->table('tbl_sale_customer')
                        ->updateOrInsert(
                            ['customer_id' => $customer->customer_id],
                            (array) $customer
                        );

                    DB::connection('oracle')
                        ->table('tbl_sale_customer')
                        ->where('customer_id', $customer->customer_id)
                        ->update(['is_pushed' => 'Y']);

                    DB::connection('oracle_live')->commit();
                    Log::info("Customer ID {$customer->customer_id} ({$customer->customer_name}) synced successfully.");
                } catch (\Exception $e) {
                    DB::connection('oracle_live')->rollBack();
                    Log::error("Failed syncing customer ID {$customer->customer_id}: " . $e->getMessage());
                }
            }

            Log::info('SyncCustomersJob completed successfully.');
        } catch (\Exception $e) {
            Log::error("SyncCustomersJob failed: " . $e->getMessage());
        }
    }
}


