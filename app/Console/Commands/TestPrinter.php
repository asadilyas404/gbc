<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderPrintService;
use App\Services\PrinterService;

class TestPrinter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printer:test
                            {printer? : Printer connection string}
                            {--type=bill_print : Printer type from database (bill_print, kitchen_print)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test printer connection';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $printer = $this->argument('printer');
        $printerType = $this->option('type');

        // Use database printer if no direct printer connection provided
        if (!$printer) {
            try {
                $printService = OrderPrintService::createFromDatabase($printerType);
                $printerName = PrinterService::getPrinterName($printerType);
                $connection = PrinterService::getPrinterConnection($printerType);
                $this->info("Testing database printer: {$printerName}");
                $this->info("Connection: {$connection}");
            } catch (\Exception $e) {
                $this->error("Failed to get printer from database: " . $e->getMessage());
                return 1;
            }
        } else {
            $printService = new OrderPrintService($printer);
            $this->info("Testing printer connection: {$printer}");
        }

        try {
            $success = $printService->testConnection();

            if ($success) {
                $this->info("✓ Printer connection successful!");
                return 0;
            } else {
                $this->error("✗ Printer connection failed!");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Error: " . $e->getMessage());
            return 1;
        }
    }
}
