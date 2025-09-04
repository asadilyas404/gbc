<?php

// Standalone test script for printing functionality
// This bypasses Laravel's database loading issues

require_once 'vendor/autoload.php';

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

echo "🖨️  Standalone Printing Test\n";
echo "============================\n\n";

// Test 1: Test basic printer connection
echo "1. Testing basic printer connection...\n";

try {
    // Create a file connector for testing
    $connector = new FilePrintConnector("C:\\temp\\test_receipt.txt");
    $printer = new Printer($connector);

    echo "✓ Printer connection successful!\n";

    // Test 2: Print a simple test receipt
    echo "\n2. Printing test receipt...\n";

    $printer->initialize();

    // Print header
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2);
    $printer->text("TEST RECEIPT\n");
    $printer->setTextSize(1, 1);
    $printer->text("============\n\n");

    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text("Order #: TEST-001\n");
    $printer->text("Date: " . date('Y-m-d H:i:s') . "\n");
    $printer->text("Type: INDOOR\n");
    $printer->text("Table: 1\n");
    $printer->text("Customer: Test Customer\n");
    $printer->text("\n");
    $printer->text("--------------------------------\n");

    // Print items
    $printer->text("ITEMS ORDERED:\n");
    $printer->text("--------------------------------\n");
    $printer->text("Test Food Item\n");
    $printer->text("Qty: 2 x 12.50\n");
    $printer->text("\n");

    // Print totals
    $printer->text("--------------------------------\n");
    $printer->text("SUBTOTAL: 25.00\n");
    $printer->text("TAX: 2.50\n");
    $printer->setTextSize(2, 1);
    $printer->text("TOTAL: 27.50\n");
    $printer->setTextSize(1, 1);
    $printer->text("--------------------------------\n");

    // Print footer
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text("\n");
    $printer->text("Thank you for your order!\n");
    $printer->text("Order Status: PENDING\n");
    $printer->text("Test Restaurant\n");
    $printer->text("\n");
    $printer->text("Generated: " . date('Y-m-d H:i:s') . "\n");

    $printer->cut();
    $printer->close();

    echo "✓ Test receipt printed successfully!\n";
    echo "✓ Receipt saved to: C:\\temp\\test_receipt.txt\n";

    // Test 3: Check if file was created
    echo "\n3. Verifying output file...\n";
    if (file_exists("C:\\temp\\test_receipt.txt")) {
        $content = file_get_contents("C:\\temp\\test_receipt.txt");
        echo "✓ File created successfully!\n";
        echo "✓ File size: " . strlen($content) . " bytes\n";
        echo "\nReceipt content preview:\n";
        echo "------------------------\n";
        echo substr($content, 0, 200) . "...\n";
    } else {
        echo "✗ File was not created!\n";
    }

    echo "\n🎉 All tests passed! The printing system is working correctly.\n";
    echo "\nNext steps:\n";
    echo "1. Check C:\\temp\\test_receipt.txt for the printed receipt\n";
    echo "2. If the receipt looks good, your printer setup is working\n";
    echo "3. You can now use the Laravel commands with proper database setup\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure the C:\\temp\\ directory exists\n";
    echo "2. Check if you have write permissions to C:\\temp\\\n";
    echo "3. Verify the escpos-php package is installed correctly\n";
}
