<?php

// Standalone printing test - completely independent of Laravel
// This bypasses all database and Laravel dependencies

echo "🖨️  Standalone Printing Test\n";
echo "============================\n\n";

// Check if escpos-php is available
if (!file_exists('vendor/autoload.php')) {
    echo "❌ Error: Composer autoload not found!\n";
    echo "Please run: composer install\n";
    exit(1);
}

require_once 'vendor/autoload.php';

// Check if escpos-php classes exist
if (!class_exists('Mike42\Escpos\PrintConnectors\FilePrintConnector')) {
    echo "❌ Error: escpos-php package not installed!\n";
    echo "Please run: composer require mike42/escpos-php\n";
    exit(1);
}

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

echo "✓ Dependencies loaded successfully!\n\n";

// Test 1: Basic printer connection
echo "1. Testing basic printer connection...\n";

try {
    // Create output directory if it doesn't exist
    $outputDir = 'C:\\temp';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
        echo "✓ Created output directory: {$outputDir}\n";
    }
    
    $outputFile = $outputDir . '\\test_receipt.txt';
    $connector = new FilePrintConnector($outputFile);
    $printer = new Printer($connector);
    
    echo "✓ Printer connection successful!\n";
    echo "✓ Output file: {$outputFile}\n";
    
    // Test 2: Print a comprehensive test receipt
    echo "\n2. Printing test receipt...\n";
    
    $printer->initialize();
    
    // Print header
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->setTextSize(2, 2);
    $printer->text("RESTAURANT RECEIPT\n");
    $printer->setTextSize(1, 1);
    $printer->text("==================\n\n");
    
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text("Order #: TEST-001\n");
    $printer->text("Serial: 01-001\n");
    $printer->text("Date: " . date('Y-m-d H:i:s') . "\n");
    $printer->text("Type: INDOOR\n");
    $printer->text("Table: 1\n");
    $printer->text("Customer: Test Customer\n");
    $printer->text("Phone: 123-456-7890\n");
    $printer->text("Car: ABC-123\n");
    $printer->text("\n");
    $printer->text("--------------------------------\n");
    
    // Print items
    $printer->text("ITEMS ORDERED:\n");
    $printer->text("--------------------------------\n");
    $printer->text("Chicken Burger\n");
    $printer->text("Qty: 2 x 12.50\n");
    $printer->text("  - Extra Spicy\n");
    $printer->text("  + Extra Cheese (+2.00)\n");
    $printer->text("  + French Fries (+3.00)\n");
    $printer->text("Note: No onions please\n");
    $printer->text("\n");
    
    $printer->text("Coca Cola\n");
    $printer->text("Qty: 1 x 3.50\n");
    $printer->text("\n");
    
    // Print totals
    $printer->text("--------------------------------\n");
    $printer->text("SUBTOTAL: 28.50\n");
    $printer->text("DISCOUNT: -2.50\n");
    $printer->text("TAX: 2.60\n");
    $printer->setTextSize(2, 1);
    $printer->text("TOTAL: 28.60\n");
    $printer->setTextSize(1, 1);
    $printer->text("--------------------------------\n");
    
    // Print footer
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text("\n");
    $printer->text("Thank you for your order!\n");
    $printer->text("Order Status: PENDING\n");
    $printer->text("Test Restaurant\n");
    $printer->text("123 Main Street\n");
    $printer->text("City, State 12345\n");
    $printer->text("\n");
    $printer->text("Generated: " . date('Y-m-d H:i:s') . "\n");
    
    $printer->cut();
    $printer->close();
    
    echo "✓ Test receipt printed successfully!\n";
    
    // Test 3: Verify output file
    echo "\n3. Verifying output file...\n";
    
    if (file_exists($outputFile)) {
        $content = file_get_contents($outputFile);
        $fileSize = strlen($content);
        echo "✓ File created successfully!\n";
        echo "✓ File size: {$fileSize} bytes\n";
        
        // Show first few lines of the receipt
        $lines = explode("\n", $content);
        $previewLines = array_slice($lines, 0, 10);
        
        echo "\nReceipt content preview:\n";
        echo "------------------------\n";
        foreach ($previewLines as $line) {
            echo $line . "\n";
        }
        echo "...\n";
        
        // Check if it looks like a proper receipt
        if (strpos($content, 'RESTAURANT RECEIPT') !== false && 
            strpos($content, 'TOTAL:') !== false) {
            echo "✓ Receipt format looks correct!\n";
        } else {
            echo "⚠ Receipt format may be incorrect\n";
        }
        
    } else {
        echo "❌ File was not created!\n";
        exit(1);
    }
    
    echo "\n🎉 All tests passed! The printing system is working correctly.\n";
    echo "\n📋 Next Steps:\n";
    echo "1. Check {$outputFile} for the printed receipt\n";
    echo "2. If the receipt looks good, your printer setup is working\n";
    echo "3. You can now configure your actual printer connection\n";
    echo "4. For USB printers, use: file:///dev/usb/lp0 (Linux) or file://COM1 (Windows)\n";
    echo "5. For network printers, use: network://192.168.1.100:9100\n";
    
    echo "\n🔧 To use with your Laravel application:\n";
    echo "1. Fix your Oracle database connection issue\n";
    echo "2. Or modify the printing commands to work without database\n";
    echo "3. Use the OrderPrintService class with proper printer connections\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Make sure the C:\\temp\\ directory exists and is writable\n";
    echo "2. Check if you have write permissions to C:\\temp\\\n";
    echo "3. Verify the escpos-php package is installed: composer require mike42/escpos-php\n";
    echo "4. Check PHP version compatibility\n";
    exit(1);
}
