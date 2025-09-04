<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderPrintService;
use App\Services\PrinterService;

class TestPrinting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printing:test
                            {--printer= : Printer connection string}
                            {--type=bill_print : Printer type from database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test printing functionality without database dependency';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Printing System...');
        $this->info('========================');

        $printer = $this->option('printer');
        $printerType = $this->option('type');

        // Test 1: List available printers
        $this->info("\n1. Testing printer configuration...");
        try {
            $printers = PrinterService::listPrinters();
            if (!empty($printers)) {
                $this->info("✓ Found " . count($printers) . " configured printers:");
                foreach ($printers as $type => $printer) {
                    $this->info("  - {$type}: {$printer['name']} -> {$printer['connection']}");
                }
            } else {
                $this->warn("⚠ No printers found in database, using fallback configuration");
            }
        } catch (\Exception $e) {
            $this->warn("⚠ Database error: " . $e->getMessage());
            $this->info("Using fallback printer configuration...");
        }

        // Test 2: Test printer connection
        $this->info("\n2. Testing printer connection...");
        try {
            if ($printer) {
                $printService = new OrderPrintService($printer);
                $this->info("Testing direct connection: {$printer}");
            } else {
                $printService = OrderPrintService::createFromDatabase($printerType);
                $printerName = PrinterService::getPrinterName($printerType);
                $this->info("Testing database printer: {$printerName}");
            }

            $success = $printService->testConnection();

            if ($success) {
                $this->info("✓ Printer connection successful!");
            } else {
                $this->error("✗ Printer connection failed!");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Printer test failed: " . $e->getMessage());
            return 1;
        }

        // Test 3: Test receipt printing
        $this->info("\n3. Testing receipt printing...");
        try {
            // Create a mock order for testing
            $mockOrder = $this->createMockOrder();

            $success = $printService->printOrder($mockOrder);

            if ($success) {
                $this->info("✓ Test receipt printed successfully!");
            } else {
                $this->error("✗ Test receipt printing failed!");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Receipt printing failed: " . $e->getMessage());
            return 1;
        }

        $this->info("\n🎉 All tests passed! Your printing system is working correctly.");
        return 0;
    }

    /**
     * Create a mock order for testing
     */
    private function createMockOrder()
    {
        $mockOrder = new \stdClass();
        $mockOrder->id = 'TEST-001';
        $mockOrder->order_serial = '01-001';
        $mockOrder->created_at = now();
        $mockOrder->order_type = 'indoor';
        $mockOrder->table_id = 1;
        $mockOrder->order_status = 'pending';
        $mockOrder->order_amount = 25.50;
        $mockOrder->total_tax_amount = 2.55;
        $mockOrder->delivery_charge = 0;
        $mockOrder->restaurant_discount_amount = 0;

        // Mock restaurant
        $mockOrder->restaurant = (object) [
            'name' => 'Test Restaurant'
        ];

        // Mock POS details
        $mockOrder->pos_details = (object) [
            'customer_name' => 'Test Customer',
            'phone' => '123-456-7890',
            'car_number' => 'ABC-123'
        ];

        // Mock order details
        $mockOrder->details = collect([
            (object) [
                'food_details' => json_encode([
                    'name' => 'Test Food Item',
                    'price' => 12.50
                ]),
                'quantity' => 2,
                'price' => 12.50,
                'variation' => json_encode([]),
                'add_ons' => json_encode([]),
                'notes' => 'Test order for printing'
            ]
        ]);

        return $mockOrder;
    }
}
