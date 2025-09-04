<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\OrderPrintService;
use App\Services\PrinterService;

class PrintOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
        protected $signature = 'order:print
                            {order_id : Order ID to print}
                            {--printer= : Printer connection string}
                            {--printer-type=bill_print : Printer type from database (bill_print, kitchen_print)}
                            {--mark-printed : Mark order as printed after successful print}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a specific order receipt';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');
        $printer = $this->option('printer');
        $printerType = $this->option('printer-type');
        $markPrinted = $this->option('mark-printed');

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

        $order = Order::with(['details.food', 'restaurant', 'pos_details'])->find($orderId);

        if (!$order) {
            $this->error("Order #{$orderId} not found.");
            return 1;
        }

        $this->info("Printing order #{$orderId}...");

        try {
            $success = $printService->printOrder($order);

            if ($success) {
                $this->info("✓ Order #{$orderId} printed successfully.");

                if ($markPrinted) {
                    $order->update(['printed' => true]);
                    $this->info("✓ Order marked as printed.");
                }

                return 0;
            } else {
                $this->error("✗ Failed to print order #{$orderId}.");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Error printing order #{$orderId}: " . $e->getMessage());
            return 1;
        }
    }
}
