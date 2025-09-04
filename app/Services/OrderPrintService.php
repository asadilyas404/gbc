<?php

namespace App\Services;

use App\Models\Order;
use App\Services\PrinterService;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Exception;

class OrderPrintService
{
    protected $printer;
    protected $connector;

    public function __construct($printerConnection)
    {
        $this->connector = $this->createConnector($printerConnection);
        $this->printer = new Printer($this->connector);
    }

    /**
     * Create OrderPrintService using database printer name
     */
    public static function createFromDatabase($printerType = 'bill_print')
    {
        $connection = PrinterService::getPrinterConnection($printerType);

        if (!$connection) {
            throw new Exception("No printer configured for type: {$printerType}");
        }

        return new self($connection);
    }

    /**
     * Create printer connector based on connection string
     */
    protected function createConnector($connectionString)
    {
        $parsed = parse_url($connectionString);

        if (!$parsed) {
            throw new Exception("Invalid printer connection string: {$connectionString}");
        }

        $scheme = $parsed['scheme'] ?? 'file';

        switch ($scheme) {
            case 'file':
                $path = $parsed['path'] ?? '/dev/usb/lp0';
                return new FilePrintConnector($path);

            case 'network':
                $host = $parsed['host'] ?? 'localhost';
                $port = $parsed['port'] ?? 9100;
                return new NetworkPrintConnector($host, $port);

            default:
                throw new Exception("Unsupported printer scheme: {$scheme}");
        }
    }

    /**
     * Print an order receipt
     */
    public function printOrder(Order $order)
    {
        try {
            $this->printer->initialize();

            // Print header
            $this->printHeader($order);

            // Print order details
            $this->printOrderDetails($order);

            // Print totals
            $this->printTotals($order);

            // Print footer
            $this->printFooter($order);

            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (Exception $e) {
            error_log("Print error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Print receipt header
     */
    protected function printHeader(Order $order)
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->setTextSize(2, 2);
        $this->printer->text("RESTAURANT RECEIPT\n");
        $this->printer->setTextSize(1, 1);
        $this->printer->text("==================\n\n");

        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        $this->printer->text("Order #: {$order->id}\n");
        $this->printer->text("Serial: {$order->order_serial}\n");
        $this->printer->text("Date: " . $order->created_at->format('Y-m-d H:i:s') . "\n");
        $this->printer->text("Type: " . strtoupper($order->order_type) . "\n");

        if ($order->table_id) {
            $this->printer->text("Table: {$order->table_id}\n");
        }

        if ($order->pos_details) {
            if ($order->pos_details->customer_name) {
                $this->printer->text("Customer: {$order->pos_details->customer_name}\n");
            }
            if ($order->pos_details->phone) {
                $this->printer->text("Phone: {$order->pos_details->phone}\n");
            }
            if ($order->pos_details->car_number) {
                $this->printer->text("Car: {$order->pos_details->car_number}\n");
            }
        }

        $this->printer->text("\n");
        $this->printer->text("--------------------------------\n");
    }

    /**
     * Print order items
     */
    protected function printOrderDetails(Order $order)
    {
        $this->printer->text("ITEMS ORDERED:\n");
        $this->printer->text("--------------------------------\n");

        foreach ($order->details as $detail) {
            $food = json_decode($detail->food_details, true);
            $foodName = $food['name'] ?? 'Unknown Item';

            // Print main item
            $this->printer->text($foodName . "\n");
            $this->printer->text("Qty: {$detail->quantity} x " . number_format($detail->price, 2) . "\n");

            // Print variations if any
            $variations = json_decode($detail->variation, true);
            if ($variations && is_array($variations)) {
                foreach ($variations as $variation) {
                    if (isset($variation['values']) && is_array($variation['values'])) {
                        foreach ($variation['values'] as $value) {
                            if (isset($value['label'])) {
                                $this->printer->text("  - " . $value['label']);
                                if (isset($value['optionPrice']) && $value['optionPrice'] > 0) {
                                    $this->printer->text(" (+" . number_format($value['optionPrice'], 2) . ")");
                                }
                                $this->printer->text("\n");
                            }
                        }
                    }
                }
            }

            // Print addons if any
            $addons = json_decode($detail->add_ons, true);
            if ($addons && is_array($addons)) {
                foreach ($addons as $addon) {
                    if (isset($addon['name'])) {
                        $this->printer->text("  + " . $addon['name']);
                        if (isset($addon['price']) && $addon['price'] > 0) {
                            $this->printer->text(" (" . number_format($addon['price'], 2) . ")");
                        }
                        $this->printer->text("\n");
                    }
                }
            }

            // Print notes if any
            if ($detail->notes) {
                $this->printer->text("Note: {$detail->notes}\n");
            }

            $this->printer->text("\n");
        }
    }

    /**
     * Print order totals
     */
    protected function printTotals(Order $order)
    {
        $this->printer->text("--------------------------------\n");
        $this->printer->text("SUBTOTAL: " . number_format($order->order_amount - $order->total_tax_amount - $order->delivery_charge, 2) . "\n");

        if ($order->restaurant_discount_amount > 0) {
            $this->printer->text("DISCOUNT: -" . number_format($order->restaurant_discount_amount, 2) . "\n");
        }

        if ($order->delivery_charge > 0) {
            $this->printer->text("DELIVERY: " . number_format($order->delivery_charge, 2) . "\n");
        }

        if ($order->total_tax_amount > 0) {
            $this->printer->text("TAX: " . number_format($order->total_tax_amount, 2) . "\n");
        }

        $this->printer->setTextSize(2, 1);
        $this->printer->text("TOTAL: " . number_format($order->order_amount, 2) . "\n");
        $this->printer->setTextSize(1, 1);
        $this->printer->text("--------------------------------\n");
    }

    /**
     * Print receipt footer
     */
    protected function printFooter(Order $order)
    {
        $this->printer->setJustification(Printer::JUSTIFY_CENTER);
        $this->printer->text("\n");
        $this->printer->text("Thank you for your order!\n");
        $this->printer->text("Order Status: " . strtoupper($order->order_status) . "\n");

        if ($order->restaurant) {
            $this->printer->text($order->restaurant->name . "\n");
        }

        $this->printer->text("\n");
        $this->printer->text("Generated: " . now()->format('Y-m-d H:i:s') . "\n");
    }

    /**
     * Test printer connection
     */
    public function testConnection()
    {
        try {
            $this->printer->initialize();
            $this->printer->text("Printer Test\n");
            $this->printer->text("Connection successful!\n");
            $this->printer->cut();
            $this->printer->close();
            return true;
        } catch (Exception $e) {
            error_log("Printer test failed: " . $e->getMessage());
            return false;
        }
    }
}
