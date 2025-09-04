<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\OrderPrintService;
use App\Services\PrinterService;
use Carbon\Carbon;

class AutoPrintOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-print
                            {--printer= : Printer connection string (e.g., "file:///dev/usb/lp0" or "network://192.168.1.100:9100")}
                            {--printer-type=bill_print : Printer type from database (bill_print, kitchen_print)}
                            {--limit= : Maximum number of orders to process at once}
                            {--seconds= : Process orders from the last N seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically print orders that haven\'t been printed yet';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $printer = $this->option('printer');
        $printerType = $this->option('printer-type');
        $limit = (int) $this->option('limit') ?: config('printing.auto_print.max_orders_per_run');
        $seconds = (int) $this->option('seconds') ?: config('printing.auto_print.interval_seconds');

        // Use database printer if no direct printer connection provided
        if (!$printer) {
            try {
                $printService = OrderPrintService::createFromDatabase($printerType);
                $printerName = PrinterService::getPrinterName($printerType);
                $this->info("Using database printer: {$printerName}");
            } catch (\Exception $e) {
                $this->error("Failed to get printer from database: " . $e->getMessage());
                $this->error('Use --printer option or configure printers in database');
                return 1;
            }
        } else {
            $printService = new OrderPrintService($printer);
        }

        $this->info("Looking for unprinted orders from the last {$seconds} seconds...");

        // Get orders that haven't been printed and are from the last N seconds
        try {
            $orders = Order::where('printed', false)
                ->where('created_at', '>=', Carbon::now()->subSeconds($seconds))
                ->with(['details.food', 'restaurant', 'pos_details'])
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            $this->error("Database connection error: " . $e->getMessage());
            $this->info("Using fallback printer configuration...");
            $orders = collect(); // Empty collection if database is not accessible
        }

        if ($orders->isEmpty()) {
            $this->info('No unprinted orders found.');
            return 0;
        }

        $this->info("Found {$orders->count()} orders to print.");

        $successCount = 0;
        $errorCount = 0;

        foreach ($orders as $order) {
            try {
                $this->info("Printing order #{$order->id}...");

                $success = $printService->printOrder($order);

                if ($success) {
                    // Mark order as printed
                    $order->update(['printed' => true]);
                    $successCount++;
                    $this->info("✓ Order #{$order->id} printed successfully.");
                } else {
                    $errorCount++;
                    $this->error("✗ Failed to print order #{$order->id}.");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("✗ Error printing order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("\nPrint Summary:");
        $this->info("Successfully printed: {$successCount} orders");
        $this->info("Failed to print: {$errorCount} orders");

        return 0;
    }
}
