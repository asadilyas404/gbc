<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PrinterService;

class ListPrinters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'printers:list {--test : Test printer connections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all printers configured in the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Database Printer Configuration');
        $this->info('=============================');

        $printers = PrinterService::listPrinters();

        if (empty($printers)) {
            $this->warn('No printers configured in database.');
            $this->info('Configure printers in business_settings table with key "print_keys"');
            return 0;
        }

        $headers = ['Type', 'Printer Name', 'Connection String', 'Status'];
        $rows = [];

        foreach ($printers as $type => $printer) {
            $status = 'Unknown';

            if ($this->option('test')) {
                $status = PrinterService::testPrinter($type) ? '✓ Working' : '✗ Failed';
            }

            $rows[] = [
                $type,
                $printer['name'],
                $printer['connection'],
                $status
            ];
        }

        $this->table($headers, $rows);

        if ($this->option('test')) {
            $this->info("\nPrinter connection test completed.");
        } else {
            $this->info("\nUse --test option to test printer connections.");
        }

        return 0;
    }
}
