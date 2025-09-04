<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class PrinterService
{
    /**
     * Get printer names from database
     */
    public static function getPrinterNames()
    {
        try {
            $setting = DB::table('business_settings')->where('key', 'print_keys')->first();

            if (!$setting || !$setting->value) {
                return null;
            }

            return json_decode($setting->value, true);
        } catch (\Exception $e) {
            // Fallback to hardcoded printers if database is not accessible
            return [
                'bill_print' => 'Hewlett-Packard HP LaserJet P2035n',
                'kitchen_print' => 'Hewlett-Packard HP LaserJet P2035n'
            ];
        }
    }

    /**
     * Get specific printer name by type
     */
    public static function getPrinterName($type = 'bill_print')
    {
        $printers = self::getPrinterNames();

        if (!$printers || !isset($printers[$type])) {
            return null;
        }

        return $printers[$type];
    }

    /**
     * Convert printer name to connection string
     * This method maps common printer names to their connection strings
     */
    public static function getConnectionString($printerName, $type = 'bill_print')
    {
        if (!$printerName) {
            return null;
        }

        // Convert printer name to a connection string
        // This is a mapping based on common printer naming patterns
        $printerName = strtolower(trim($printerName));

        // Check if it's a network printer (contains IP or network indicators)
        if (preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $printerName, $matches)) {
            // Extract IP address and use port 9100
            return "network://{$matches[1]}:9100";
        }

        // Check for common network printer patterns
        if (strpos($printerName, 'network') !== false || strpos($printerName, 'ip') !== false) {
            // Try to extract IP from the name or use a default
            return "network://192.168.1.100:9100";
        }

        // Check for USB/Serial printer patterns
        if (strpos($printerName, 'usb') !== false || strpos($printerName, 'serial') !== false) {
            return "file:///dev/usb/lp0";
        }

        // Check for specific printer models and map to common connection strings
        $printerMappings = [
            'hp laserjet' => 'file:///dev/usb/lp0',
            'canon' => 'file:///dev/usb/lp0',
            'epson' => 'file:///dev/usb/lp0',
            'thermal' => 'file:///dev/usb/lp0',
            'receipt' => 'file:///dev/usb/lp0',
            'kitchen' => 'file:///dev/usb/lp1',
        ];

        foreach ($printerMappings as $pattern => $connection) {
            if (strpos($printerName, $pattern) !== false) {
                return $connection;
            }
        }

        // Default fallback
        return "file:///dev/usb/lp0";
    }

    /**
     * Get connection string for a specific printer type
     */
    public static function getPrinterConnection($type = 'bill_print')
    {
        $printerName = self::getPrinterName($type);

        if (!$printerName) {
            return null;
        }

        return self::getConnectionString($printerName, $type);
    }

    /**
     * List all available printers with their connection strings
     */
    public static function listPrinters()
    {
        $printers = self::getPrinterNames();

        if (!$printers) {
            return [];
        }

        $result = [];
        foreach ($printers as $type => $name) {
            $result[$type] = [
                'name' => $name,
                'connection' => self::getConnectionString($name, $type)
            ];
        }

        return $result;
    }

    /**
     * Test if a printer is available
     */
    public static function testPrinter($type = 'bill_print')
    {
        $connection = self::getPrinterConnection($type);

        if (!$connection) {
            return false;
        }

        try {
            $printService = new OrderPrintService($connection);
            return $printService->testConnection();
        } catch (Exception $e) {
            return false;
        }
    }
}
