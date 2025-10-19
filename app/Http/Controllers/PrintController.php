<?php

namespace App\Http\Controllers;

use App\Models\OptionsList;
use I18N_Arabic;
use App\Models\Order;
use Mike42\Escpos\Printer;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\DB;
use App\Helpers\ReceiptImageHelper;
use App\Models\AddOn;
use App\Models\Food;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use PhpParser\Node\Stmt\TryCatch;

class PrintController extends Controller
{
    public function printOrder(Request $request)
    {

        /**
         * Generate a single formatted row with aligned columns (multibyte-safe)
         *
         * @param array $columns  Array of column texts
         * @param array $widths   Array of column widths (in characters)
         * @param array $aligns   Array of 'left', 'right', 'center' for each column (optional)
         * @return string         Formatted line ready to print
         */
        
            function formatPrintRow(array $columns, array $widths, array $aligns = [])
            {
                $line = '';
                $count = count($widths);

                for ($i = 0; $i < $count; $i++) {
                    $text = isset($columns[$i]) ? (string) $columns[$i] : '';
                    $width = $widths[$i];
                    $align = isset($aligns[$i]) ? $aligns[$i] : 'left';

                    // Handle multibyte strings
                    $textLength = mb_strlen($text, 'UTF-8');

                    // Truncate if too long
                    if ($textLength > $width) {
                        $text = mb_substr($text, 0, $width, 'UTF-8');
                        $textLength = $width;
                    }

                    $padLength = $width - $textLength;

                    switch ($align) {
                        case 'right':
                            $padded = str_repeat(' ', $padLength) . $text;
                            break;
                        case 'center':
                            $leftPad = floor($padLength / 2);
                            $rightPad = $padLength - $leftPad;
                            $padded = str_repeat(' ', $leftPad) . $text . str_repeat(' ', $rightPad);
                            break;
                        case 'left':
                        default:
                            $padded = $text . str_repeat(' ', $padLength);
                            break;
                    }

                    $line .= $padded;
                }

                return $line;
            }
        

        function getRowLine(array $columns, array $widths)
        {
            $line = '';
            foreach ($columns as $i => $text) {
                // pad or cut each column
                $line .= str_pad(mb_strimwidth($text, 0, $widths[$i]), $widths[$i]);
            }

            return $line;
        }

        try {
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $orderId = $request->input('order_id') ?: $request->query('order_id');

            // Find the order
            $order = Order::with(['restaurant', 'details.food', 'takenBy', 'pos_details'])
                ->where('id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Get printer name from database
            $user = Auth::user();

            $branchId = $order->restaurant_id;
            $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
            $printerName = $branch->bill_printer ?? 'BillPrinter';

            // Connect to printer
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Arabic text
            $currencyText = "ر.ع"; // "مرحبا بكم في مالك البيتزا"; // "Welcome to Malek Pizza"
            $currencyImagePath = storage_path('app/public/prints/arabic_currency_text.png');
            ReceiptImageHelper::createArabicImageForPrinter($currencyText, $currencyImagePath, 16);
            $currencyTextimage = EscposImage::load($currencyImagePath);

            // End of image print
            $linedash = "------------------------------------------------\n";
            // Print order header
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $printer->text($order->restaurant->name . "\n");
            // $printer->text(mb_convert_encoding("مرحبا مرحبا مرحبا", "CP864", "UTF-8"));
            $printer->setTextSize(1, 1);
            $printer->text($order->restaurant->address . "\n");
            $printer->text("Phone: " . $order->restaurant->phone . "\n");
            $printer->text($linedash);
            //  $printer->feed(1);

            // Order details
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            //                         $printer->setTextSize(2, 2);

            //                         $lineWidth = 42; // adjust to your printer (try 32 if text gets cut)

            // // Left + right texts
            // $left = "Order #: " . $order->order_serial ;
            // $right  = "Date: " . date('Y-m-d H:i', strtotime($order->created_at));

            // Create one line with padding
            // $line = $left . str_repeat(" ", max(0, $lineWidth - strlen($left) - strlen($right))) . $right;

            //$printer->text($line . "\n");
            $printer->setTextSize(2, 2);
            $printer->text("Order # " . $order->order_serial . "\n");
            $printer->setTextSize(1, 1);

            $printer->text("Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n");
            $printer->text("Order Type: " . ucfirst($order->order_type) . "\n");

            // Customer info
            if ($order->pos_details) {
                $customerName = $order->pos_details->customer_name ?: 'Walk-in Customer';
                $printer->text("Customer: " . $customerName . "\n");

                if ($order->pos_details->phone) {
                    $printer->text("Phone: " . $order->pos_details->phone . "\n");
                }

                if ($order->pos_details->car_number) {
                    $printer->text("Car No: " . $order->pos_details->car_number . "\n");
                }
            }

            if ($order->takenBy) {
                $printer->text("Order By: " . $order->takenBy->f_name . " " . $order->takenBy->l_name . "\n");
            }

            $printer->text($linedash);

            // Define column widths (adjust for your printer, usually 42 for 80mm)
            $colWidths = [5, 30, 12];           // Qty, Name, Amount
            $colAligns = ['left', 'left', 'right'];  // Amount right-aligned

            $colArabicImagePath = public_path('assets/pos_printer_arabic_header_text.png');
            $headerFilePath = EscposImage::load($colArabicImagePath, false);

            // Items

            $printer->text(formatPrintRow(["Qty", "Name", "Price"], $colWidths, $colAligns) . "\n");
            $printer->bitImageColumnFormat($headerFilePath);
            $qtyImage = ReceiptImageHelper::createArabicImageForPrinter("كمية", storage_path('app/public/prints/food_qty_arabic.png'), 20);
            $qtyImage = EscposImage::load($qtyImage, false);
            $nameImage = ReceiptImageHelper::createArabicImageForPrinter("اسم", storage_path('app/public/prints/food_name_arabic.png'), 20);
            $nameImage = EscposImage::load($nameImage, false);
            $priceImage = ReceiptImageHelper::createArabicImageForPrinter("الكمية", storage_path('app/public/prints/food_price_arabic.png'), 20);
            $priceImage = EscposImage::load($priceImage, false);

            $printer->text($linedash);

            $subTotal = 0;
            $addOnsCost = 0;
            $count = 0;
            foreach ($order->details as $detail) {
                if ($detail->food_id || $detail->campaign == null) {
                    $foodDetails = json_decode($detail->food_details, true);

                    $foodName = $foodDetails['name'] ?? 'Unknown Item';
                    $foodArabicName = Food::where('id', $detail->food_id)->first()->getTranslationValue('name', 'ar');
                    $foodArabicName = ReceiptImageHelper::createArabicImageForPrinter($foodArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                    $foodArabicNameImage = EscposImage::load($foodArabicName, false);

                    $printer->setEmphasis(true);
                    $printer->text(formatPrintRow([$detail->quantity, $foodName, number_format($detail->price, 3, '.', '')], $colWidths, $colAligns) . "\n");

                    // Arabic price line
                    // $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setPrintLeftMargin(55);
                    $printer->bitImageColumnFormat($foodArabicNameImage);
                    $printer->setPrintLeftMargin(0);
                    // Variations
                    $variations = json_decode($detail->variation, true);
                    if (count($variations) > 0) {
                        //$printer->text("  Variations:\n");

                        foreach ($variations as $variation) {
                            if (isset($variation['name']) && isset($variation['values'])) {
                                $printer->text("  " . $variation['name'] . ":" . "\n");
                                // $printer->text("  " . $variation['options_list_id'] . ":");
                                foreach ($variation['values'] as $value) {
                                    //$printer->text(" - " . $value['label'] . " (" . Helpers::format_currency($value['optionPrice']) . ")\n");
                                    //$printer->text(" - " . $value['label'] . " (" . number_format($value['optionPrice'], 3, '.', '') . ")\n");
                                    $options_listname = DB::table('options_list')
                                        ->where('id', $value['options_list_id'])
                                        ->value('name');
                                    $printer->text("  - " . $options_listname . " (" . number_format($value['optionPrice'], 3, '.', '') . ")\n");
                                    // Get Option Translation

                                    $arabicOptionName = OptionsList::where('id', $value['options_list_id'])->first()->getTranslationValue('name', 'ar') ?? '';

                                    if ($arabicOptionName) {
                                        $arabicOptionName = ReceiptImageHelper::createArabicImageForPrinter($arabicOptionName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                        $arabicOptionName = EscposImage::load($arabicOptionName, false);
                                        $printer->setPrintLeftMargin(40);
                                        $printer->bitImageColumnFormat($arabicOptionName);
                                        $printer->setPrintLeftMargin(0);
                                    }
                                }
                            }

                            //variation addon

                            // Print addons if available
                            if (isset($variation['addons']) && count($variation['addons']) > 0) {
                                foreach ($variation['addons'] as $addon) {
                                    $printer->text("     V Addon: " . $addon['name']
                                        . " | (" . number_format($addon['price'], 3, '.', '')
                                        . " x " . $addon['quantity'] . ")\n");

                                    $addOnArabicName = AddOn::where('id', $addon['id'])->first()->getTranslationValue('name', 'ar') ?? '';

                                    if ($addOnArabicName) {
                                        $addOnArabicName = ReceiptImageHelper::createArabicImageForPrinter($addOnArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                        $addOnArabicName = EscposImage::load($addOnArabicName, false);
                                        $printer->setPrintLeftMargin(40);
                                        $printer->bitImageColumnFormat($addOnArabicName);
                                        $printer->setPrintLeftMargin(0);
                                    }

                                    $addOnsCost += $addon['price'] * $addon['quantity'];
                                }
                            }
                        }
                    }


                    $printer->setEmphasis(false);
                    // Add-ons
                    $addOns = json_decode($detail->add_ons, true);
                    if (count($addOns) > 0) {
                        $printer->text("  Add-ons:" . "\n");
                        foreach ($addOns as $addon) {
                            // $printer->text("    - " . $addon['name'] . " (" . $addon['quantity'] . "x" . Helpers::format_currency($addon['price']) . ")\n");
                            $printer->text("  -" . $addon['name'] . " (" . $addon['quantity'] . "x" . number_format($addon['price'], 3, '.', '') . ")\n");

                            // Get Addon Translation
                            $addOnArabicName = AddOn::where('id', $addon['id'])->first()->getTranslationValue('name', 'ar') ?? '';
                            if ($addOnArabicName) {
                                $addOnArabicName = ReceiptImageHelper::createArabicImageForPrinter($addOnArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                $addOnArabicName = EscposImage::load($addOnArabicName, false);
                                $printer->setPrintLeftMargin(40);
                                $printer->bitImageColumnFormat($addOnArabicName);
                                $printer->setPrintLeftMargin(0);
                            }

                            $addOnsCost += $addon['price'] * $addon['quantity'];
                        }
                    }

                    // Notes
                    if ($detail->notes) {
                        $printer->text("  Note: \n");
                        $notes = ReceiptImageHelper::createArabicImageForPrinter($detail->notes, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                        $notes = EscposImage::load($notes, false);
                        $printer->setPrintLeftMargin(40);
                        $printer->bitImageColumnFormat($notes);
                        $printer->setPrintLeftMargin(0);
                    }

                    $itemTotal = $detail->price * $detail->quantity;
                    $subTotal += $itemTotal;

                    //$printer->text("  Total: " . Helpers::format_currency($itemTotal) . "\n");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setEmphasis(true);
                    $printer->text("  Total: " . number_format($itemTotal, 3, '.', '') . "\n");
                    $printer->setJustification(Printer::JUSTIFY_LEFT);

                    $printer->text($linedash);
                }
            }

            // Order summary
            $printer->text("Items Price: " . number_format($subTotal, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            // $printer->text("\n");
            $printer->text("Add-ons: " . number_format($addOnsCost, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            $subTotalWithAddons = $subTotal + $addOnsCost;
            $printer->text("Subtotal: " . number_format($subTotalWithAddons, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            if ($order->restaurant_discount_amount > 0) {
                $printer->text("Discount: -" . number_format($order->restaurant_discount_amount, 3, '.', ''));
                $printer->bitImageColumnFormat($currencyTextimage);
            }

            if ($order->tax_status == 'excluded' || $order->tax_status == null) {
                $printer->text("Tax: " . number_format($order->total_tax_amount, 3, '.', ''));
                $printer->bitImageColumnFormat($currencyTextimage);
            }

            $printer->text("Delivery: " . number_format($order->delivery_charge, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            if ($order->additional_charge > 0) {
                $printer->text("Additional: " . number_format($order->additional_charge, 3, '.', ''));
                $printer->bitImageColumnFormat($currencyTextimage);
            }

            $printer->text($linedash);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("TOTAL: " . number_format($order->order_amount, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            $printer->text($linedash);

            // Payment info
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Payment: " . ucfirst(str_replace('_', ' ', $order->payment_method)) . "\n");

            if ($order->pos_details) {
                $printer->text("Cash: " . number_format($order->pos_details->cash_paid, 3, '.', ''));
                $printer->bitImageColumnFormat($currencyTextimage);
                $printer->text("Card: " . number_format($order->pos_details->card_paid, 3, '.', ''));
                $printer->bitImageColumnFormat($currencyTextimage);

                $change = $order->pos_details->cash_paid + $order->pos_details->card_paid - $order->pos_details->invoice_amount;
                if ($change > 0) {
                    $printer->text("Change: " . number_format($change, 3, '.', ''));
                    $printer->bitImageColumnFormat($currencyTextimage);
                }
            }

            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Thank you for your order!\n");
            $printer->text($linedash);

            // Feed & cut
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            return redirect()->back()->with('success', 'Order printed successfully!');
        } catch (\Exception $e) {
            // Log the error but don't prevent order placement
            Log::error('Print error in printOrder: ' . $e->getMessage());

            // Return success to prevent blocking order placement
            return redirect()->back()->with('warning', 'Order placed successfully, but printing failed: ' . $e->getMessage());
        }
    }


    //kitchen print copy

    public function printOrderKitchen(Request $request)
    {

        /**
         * Generate a single formatted row with aligned columns (multibyte-safe)
         *
         * @param array $columns  Array of column texts
         * @param array $widths   Array of column widths (in characters)
         * @param array $aligns   Array of 'left', 'right', 'center' for each column (optional)
         * @return string         Formatted line ready to print
         */
        function formatRowKitchen(array $columns, array $widths, array $aligns = [])
        {
            $line = '';
            $count = count($widths);

            for ($i = 0; $i < $count; $i++) {
                $text = isset($columns[$i]) ? (string) $columns[$i] : '';
                $width = $widths[$i];
                $align = isset($aligns[$i]) ? $aligns[$i] : 'left';

                // Handle multibyte strings
                $textLength = mb_strlen($text, 'UTF-8');

                // Truncate if too long
                if ($textLength > $width) {
                    $text = mb_substr($text, 0, $width, 'UTF-8');
                    $textLength = $width;
                }

                $padLength = $width - $textLength;

                switch ($align) {
                    case 'right':
                        $padded = str_repeat(' ', $padLength) . $text;
                        break;
                    case 'center':
                        $leftPad = floor($padLength / 2);
                        $rightPad = $padLength - $leftPad;
                        $padded = str_repeat(' ', $leftPad) . $text . str_repeat(' ', $rightPad);
                        break;
                    case 'left':
                    default:
                        $padded = $text . str_repeat(' ', $padLength);
                        break;
                }

                $line .= $padded;
            }

            return $line;
        }


        function getRowLineK(array $columns, array $widths)
        {
            $line = '';
            foreach ($columns as $i => $text) {
                // pad or cut each column
                $line .= str_pad(mb_strimwidth($text, 0, $widths[$i]), $widths[$i]);
            }

            return $line;
        }

        try {
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $orderId = $request->input('order_id') ?: $request->query('order_id');

            // Find the order
            $order = Order::with(['restaurant', 'details.food', 'takenBy', 'pos_details'])
                ->where('id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Get printer name from database
            $user = Auth::user();

            $branchId = $order->restaurant_id;
            $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
            $printerName = $branch->kitchen_printer ?? 'KitchenPrinter';

            // Connect to printer
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Arabic text
            $currencyText = "ر.ع"; // "مرحبا بكم في مالك البيتزا"; // "Welcome to Malek Pizza"
            $currencyImagePath = storage_path('app/public/prints/arabic_currency_text.png');
            ReceiptImageHelper::createArabicImageForPrinter($currencyText, $currencyImagePath, 16);
            $currencyTextimage = EscposImage::load($currencyImagePath);

            // End of image print
            $linedash = "------------------------------------------------\n";
            // Print order header

            $printer->setEmphasis(true);
            if ($order->printed == '1') {
                $printer->text("REPRINTED\n\n");
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);

            $printer->setTextSize(2, 2);
            $printer->text($order->restaurant->name . "\n");

            $printer->setTextSize(1, 1);
            $printer->text($order->restaurant->address . "\n");
            $printer->text("Phone: " . $order->restaurant->phone . "\n");
            $printer->text($linedash);

            $printer->text("KITCHEN COPY\n");
            //  $printer->feed(1);

            // Order details
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            //                         $printer->setTextSize(2, 2);

            //                         $lineWidth = 42; // adjust to your printer (try 32 if text gets cut)

            // // Left + right texts
            // $left = "Order #: " . $order->order_serial ;
            // $right  = "Date: " . date('Y-m-d H:i', strtotime($order->created_at));

            // Create one line with padding
            // $line = $left . str_repeat(" ", max(0, $lineWidth - strlen($left) - strlen($right))) . $right;

            //$printer->text($line . "\n");
            $printer->setTextSize(2, 2);
            $printer->text("Order # " . $order->order_serial . "\n");
            $printer->setTextSize(1, 1);

            $printer->text("Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n");
            $printer->text("Order Type: " . ucfirst($order->order_type) . "\n");

            // Customer info
            if ($order->pos_details) {
                $customerName = $order->pos_details->customer_name ?: 'Walk-in Customer';
                $printer->text("Customer: " . $customerName . "\n");

                if ($order->pos_details->phone) {
                    $printer->text("Phone: " . $order->pos_details->phone . "\n");
                }

                if ($order->pos_details->car_number) {
                    $printer->text("Car No: " . $order->pos_details->car_number . "\n");
                }
            }

            if ($order->takenBy) {
                $printer->text("Order By: " . $order->takenBy->f_name . " " . $order->takenBy->l_name . "\n");
            }

            $printer->text($linedash);
            // $printer->feed(1);

            // Order items
            //  $printer->text("Qty" . "   " . "Item" . str_repeat(" ", 32)  . "Amount\n");

            // Define column widths (adjust for your printer, usually 42 for 80mm)
            $colWidths = [5, 30, 12];           // Qty, Name, Amount
            $colAligns = ['left', 'left', 'right'];  // Amount right-aligned
            // $bmpFile = $this->createArabicPngTight($colArabic, 'arabic-text.png');
            // $image = \Mike42\Escpos\EscposImage::load($bmpFile);

            //$printer->graphics($image);

            // Items
            $colArabicImagePath = public_path('assets/pos_printer_arabic_header_text.png');
            $headerFilePath = EscposImage::load($colArabicImagePath, false);

            // Items

            $printer->text(formatRowKitchen(["Qty", "Name", "Price"], $colWidths, $colAligns) . "\n");
            $printer->bitImageColumnFormat($headerFilePath);

            $printer->text($linedash);

            $subTotal = 0;
            $addOnsCost = 0;
            $count = 0;
            foreach ($order->details as $detail) {
                if ($detail->food_id || $detail->campaign == null) {
                    $foodDetails = json_decode($detail->food_details, true);

                    $foodName = $foodDetails['name'] ?? 'Unknown Item';
                    $foodArabicName = Food::where('id', $detail->food_id)->first()->getTranslationValue('name', 'ar');
                    $foodArabicName = ReceiptImageHelper::createArabicImageForPrinter($foodArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                    $foodArabicNameImage = EscposImage::load($foodArabicName, false);
                    //  $printer->text($detail->quantity . "x " . $foodName . "\n");

                    // Price per item
                    // $printer->text("  Price: " . Helpers::format_currency($detail->price) . "\n");
                    //  $printer->text("  G Price: " . $detail->price . "\n" );

                    $printer->setEmphasis(true);
                    $printer->text(formatRowKitchen([$detail->quantity, $foodName, number_format($detail->price, 3, '.', '')], $colWidths, $colAligns) . "\n");

                    $printer->setPrintLeftMargin(55);
                    $printer->bitImageColumnFormat($foodArabicNameImage);
                    $printer->setPrintLeftMargin(0);
                    //  $arabicText = " ر.ع". "  MG Price: " . $detail->price  ;
                    //   $filePath = $this->createArabicImage($arabicText);

                    //                        $bmpFile = $this->createArabicPngTight('ملك البيتزا', 'arabic-text.png');
                    // $image = \Mike42\Escpos\EscposImage::load($bmpFile);
                    // $printer->graphics($image);


                    //dd($filePath);
                    //  $logo = \Mike42\Escpos\EscposImage::load($filePath, false);
                    //                     $printer->graphics($logo);

                    $printer->setEmphasis(false);
                    // Variations
                    $variations = json_decode($detail->variation, true);
                    if (count($variations) > 0) {
                        //$printer->text("  Variations:\n");

                        foreach ($variations as $variation) {
                            if (isset($variation['name']) && isset($variation['values'])) {
                                $printer->text("  " . $variation['name'] . ":" . "\n");
                                // $printer->text("  " . $variation['options_list_id'] . ":");


                                foreach ($variation['values'] as $value) {
                                    //$printer->text(" - " . $value['label'] . " (" . Helpers::format_currency($value['optionPrice']) . ")\n");
                                    //$printer->text(" - " . $value['label'] . " (" . number_format($value['optionPrice'], 3, '.', '') . ")\n");
                                    $options_listname = DB::table('options_list')
                                        ->where('id', $value['options_list_id'])
                                        ->value('name');
                                    $printer->text("  - " . $options_listname . " (" . number_format($value['optionPrice'], 3, '.', '') . ")\n");

                                    $arabicOptionName = OptionsList::where('id', $value['options_list_id'])->first()->getTranslationValue('name', 'ar') ?? '';

                                    if ($arabicOptionName) {
                                        $arabicOptionName = ReceiptImageHelper::createArabicImageForPrinter($arabicOptionName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                        $arabicOptionName = EscposImage::load($arabicOptionName, false);
                                        $printer->setPrintLeftMargin(40);
                                        $printer->bitImageColumnFormat($arabicOptionName);
                                        $printer->setPrintLeftMargin(0);
                                    }
                                }
                            }

                            //variation addon

                            // Print addons if available
                            if (isset($variation['addons']) && count($variation['addons']) > 0) {
                                foreach ($variation['addons'] as $addon) {
                                    $printer->text("     V Addon: " . $addon['name']
                                        . " | (" . number_format($addon['price'], 3, '.', '')
                                        . " x " . $addon['quantity'] . ")\n");

                                    $addOnArabicName = AddOn::where('id', $addon['id'])->first()->getTranslationValue('name', 'ar') ?? '';

                                    if ($addOnArabicName) {
                                        $addOnArabicName = ReceiptImageHelper::createArabicImageForPrinter($addOnArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                        $addOnArabicName = EscposImage::load($addOnArabicName, false);
                                        $printer->setPrintLeftMargin(40);
                                        $printer->bitImageColumnFormat($addOnArabicName);
                                        $printer->setPrintLeftMargin(0);
                                    }

                                    $addOnsCost += $addon['price'] * $addon['quantity'];
                                }
                            }
                        }
                    }


                    $printer->setEmphasis(false);
                    // Add-ons
                    $addOns = json_decode($detail->add_ons, true);
                    if (count($addOns) > 0) {
                        $printer->text("  Add-ons:" . "\n");
                        foreach ($addOns as $addon) {
                            // $printer->text("    - " . $addon['name'] . " (" . $addon['quantity'] . "x" . Helpers::format_currency($addon['price']) . ")\n");
                            $printer->text("  -" . $addon['name'] . " (" . $addon['quantity'] . "x" . number_format($addon['price'], 3, '.', '') . ")\n");

                            // Get Addon Translation
                            $addOnArabicName = AddOn::where('id', $addon['id'])->first()->getTranslationValue('name', 'ar') ?? '';
                            if ($addOnArabicName) {
                                $addOnArabicName = ReceiptImageHelper::createArabicImageForPrinter($addOnArabicName, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                                $addOnArabicName = EscposImage::load($addOnArabicName, false);
                                $printer->setPrintLeftMargin(40);
                                $printer->bitImageColumnFormat($addOnArabicName);
                                $printer->setPrintLeftMargin(0);
                            }

                            $addOnsCost += $addon['price'] * $addon['quantity'];
                        }
                    }

                    // Notes
                    if ($detail->notes) {
                        $printer->text("  Note: " . $detail->notes . "\n");
                        $notes = ReceiptImageHelper::createArabicImageForPrinter($detail->notes, storage_path('app/public/prints/food_' . $count++ . '_arabic.png'), 20);
                        $notes = EscposImage::load($notes, false);
                        $printer->setPrintLeftMargin(40);
                        $printer->bitImageColumnFormat($notes);
                        $printer->setPrintLeftMargin(0);
                    }

                    $itemTotal = $detail->price * $detail->quantity;
                    $subTotal += $itemTotal;
                }
            }

            $printer->text($linedash);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("TOTAL: " . number_format($order->order_amount, 3, '.', ''));
            $printer->bitImageColumnFormat($currencyTextimage);
            $printer->text($linedash);

            // Feed & cut
            $printer->cut();
            $printer->close();

            return redirect()->back()->with('success', 'Order printed successfully!');
        } catch (\Exception $e) {
            // Log the error but don't prevent order placement
            Log::error('Print error in printOrderKitchen: ' . $e->getMessage());

            // Return success to prevent blocking order placement
            return redirect()->back()->with('warning', 'Order placed successfully, but kitchen printing failed: ' . $e->getMessage());
        }
    }

    private function createArabicPngTight($arabicText, $fileName = 'arabic-tight.png')
    {
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($arabicText);

        $fontPath = public_path('fonts/Amiri-Bold.ttf');
        $fontSize = 24;

        // Measure text bounding box
        $box = imagettfbbox($fontSize, 0, $fontPath, $reshapedText);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        $paddingX = 50; // left and right padding
        $paddingY = 50; // top and bottom padding
        $canvasWidth = $textWidth + ($paddingX * 2);
        $canvasHeight = $textHeight + ($paddingY * 2);

        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $img = $manager->canvas($canvasWidth, $canvasHeight, '#ffffff');

        // Draw text near top-left with padding
        $yPos = $paddingY + $textHeight;
        $img->text($reshapedText, $paddingX, $yPos, function ($font) use ($fontPath, $fontSize) {
            $font->file($fontPath);
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('left');
            $font->valign('top');
        });

        // Save temp PNG
        $filePath = public_path($fileName);
        $img->save($filePath);

        // 🔹 Crop only top and bottom (keep width same)
        $gd = imagecreatefrompng($filePath);

        // Scan top
        $top = 0;
        for ($y = 0; $y < imagesy($gd); $y++) {
            for ($x = 0; $x < imagesx($gd); $x++) {
                $color = imagecolorat($gd, $x, $y);
                if ($color != 0xFFFFFF) { // Not white
                    $top = $y;
                    break 2;
                }
            }
        }

        // Scan bottom
        $bottom = imagesy($gd) - 1;
        for ($y = imagesy($gd) - 1; $y >= 0; $y--) {
            for ($x = 0; $x < imagesx($gd); $x++) {
                $color = imagecolorat($gd, $x, $y);
                if ($color != 0xFFFFFF) {
                    $bottom = $y;
                    break 2;
                }
            }
        }

        $newHeight = $bottom - $top + 1;
        $cropped = imagecreatetruecolor(imagesx($gd), $newHeight);
        $white = imagecolorallocate($cropped, 255, 255, 255);
        imagefill($cropped, 0, 0, $white);

        imagecopy($cropped, $gd, 0, 0, 0, $top, imagesx($gd), $newHeight);

        imagepng($cropped, $filePath);

        imagedestroy($gd);
        imagedestroy($cropped);

        return $filePath;
    }


    private function createArabicPngExactHeightolldd($arabicText, $fileName = 'arabic-text-rr.png')
    {
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($arabicText);

        $fontPath = public_path('fonts/Amiri-Bold.ttf');
        $fontSize = 64;

        // Measure text
        $box = imagettfbbox($fontSize, 0, $fontPath, $reshapedText);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        $padding = 20; // small padding
        $canvasWidth = $textWidth + ($padding * 2);
        $canvasHeight = $textHeight + ($padding * 2);

        // Create white canvas
        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $img = $manager->canvas($canvasWidth, $canvasHeight, '#ffffff');

        // Calculate Y position using baseline
        $yPos = $padding - $box[7]; // top of bounding box + padding

        // Draw text
        $img->text($reshapedText, $padding, $yPos, function ($font) use ($fontPath, $fontSize) {
            $font->file($fontPath);
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('left');
            $font->valign('top');
        });

        $filePath = public_path($fileName);
        $img->save($filePath);

        return $filePath;
    }

    private function createArabicImagenotwork($text, $fileName = 'arabic-text4.png')
    {
        // Load Arabic shaper
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($text);

        $fontPath = public_path('fonts/Amiri-Bold.ttf');
        $fontSize = 24;

        // Measure bounding box
        $box = imagettfbbox($fontSize, 0, $fontPath, $reshapedText);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        $padding = 10;
        $canvasWidth = $textWidth + ($padding * 2);
        $canvasHeight = $textHeight + ($padding * 2);

        // Create canvas
        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $img = $manager->canvas($canvasWidth, $canvasHeight, '#ffffff');

        // Add text
        $img->text($reshapedText, $canvasWidth / 2, $canvasHeight / 2, function ($font) use ($fontPath, $fontSize) {
            $font->file($fontPath);
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Save temporary PNG
        $tmpPng = public_path('temp_arabic.png');
        $img->save($tmpPng);

        // Convert PNG → BMP with GD
        $gdImg = imagecreatefrompng($tmpPng);
        $filePath = public_path($fileName);
        imagebmp($gdImg, $filePath, 24); // 24-bit BMP
        imagedestroy($gdImg);

        // Delete temp PNG
        unlink($tmpPng);

        return $filePath;
    }

    private function createArabicPng($arabicText, $fileName = 'arabic-textggg.png')
    {
        // Reshape Arabic text
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($arabicText);

        $fontPath = public_path('fonts/Amiri-Bold.ttf');
        $fontSize = 24;

        // Measure text
        $box = imagettfbbox($fontSize, 0, $fontPath, $reshapedText);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        $padding = 10;
        $canvasWidth = $textWidth + ($padding * 2);
        $canvasHeight = $textHeight + ($padding * 2);

        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $img = $manager->canvas($canvasWidth, $canvasHeight, '#ffffff');

        // Draw text
        $img->text($reshapedText, $canvasWidth / 2, $canvasHeight / 2, function ($font) use ($fontPath, $fontSize) {
            $font->file($fontPath);
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        $filePath = public_path($fileName);
        $img->save($filePath);

        return $filePath;
    }


    private function createArabicImageworking($text, $fileName = 'arabic-text5.png')
    {
        // Load Arabic shaper
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($text);

        $fontPath = public_path('fonts/Amiri-Bold.ttf');
        $fontSize = 24;

        // Measure bounding box of text
        $box = imagettfbbox($fontSize, 0, $fontPath, $reshapedText);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);

        // Add some padding
        $padding = 10;
        $canvasWidth = $textWidth + ($padding * 2);
        $canvasHeight = $textHeight + ($padding * 2);

        // Create canvas with exact size
        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        //$manager = new \Intervention\Image\ImageManager(['driver' => 'imagick']);

        $img = $manager->canvas($canvasWidth, $canvasHeight, '#ffffff');

        // Add text (centered)
        $img->text($reshapedText, $canvasWidth / 2, $canvasHeight / 2, function ($font) use ($fontPath, $fontSize) {
            $font->file($fontPath);
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        // Save as BMP
        $filePath = public_path($fileName);
        $img->save($filePath);
        // $img->encode('bmp')->save($filePath);
        //$img->encode('bmp', 24)->save($filePath);


        return $filePath;
    }

    private function createArabicImage($text, $fileName = 'arabic-text67.png')
    {
        // Load Arabic shaper
        $arabic = new \I18N_Arabic('Glyphs');
        $reshapedText = $arabic->utf8Glyphs($text);

        // Create PNG with Intervention Image
        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $img = $manager->canvas(400, 100, '#ffffff');
        $img->text($reshapedText, 200, 50, function ($font) {
            $font->file(public_path('fonts/Amiri-Bold.ttf')); // make sure this font exists
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        $filePath = public_path($fileName);
        $img->save($filePath);
        //$img->encode('bmp')->save($filePath);


        return $filePath;
    }

    public function printOrderKitchenOLD(Request $request)
    {
        try {
            // Validate order ID
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $orderId = $request->input('order_id') ?: $request->query('order_id');

            // Find the order
            $order = Order::with(['restaurant', 'details.food', 'takenBy', 'pos_details'])
                ->where('id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Get printer name from database
            $branchId = $order->restaurant_id;
            $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
            $printerName = $branch->kitchen_printer ?? 'KitchenPrinter';

            // Connect to printer
            $connector = new WindowsPrintConnector($printerName);
            $printer = new Printer($connector);

            // Print order header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("==============================\n");
            $printer->text($order->restaurant->name . "\n");
            $printer->text($order->restaurant->address . "\n");
            $printer->text("Phone: " . $order->restaurant->phone . "\n");
            $printer->text("==============================\n");
            $printer->feed(1);

            // Order details
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Order ID: " . $order->order_serial . "\n");
            $printer->text("Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n");
            $printer->text("Order Type: " . ucfirst($order->order_type) . "\n");

            // Customer info
            if ($order->pos_details) {
                $customerName = $order->pos_details->customer_name ?: 'Walk-in Customer';
                $printer->text("Customer: " . $customerName . "\n");

                if ($order->pos_details->phone) {
                    $printer->text("Phone: " . $order->pos_details->phone . "\n");
                }

                if ($order->pos_details->car_number) {
                    $printer->text("Car No: " . $order->pos_details->car_number . "\n");
                }
            }

            if ($order->takenBy) {
                $printer->text("Order By: " . $order->takenBy->f_name . " " . $order->takenBy->l_name . "\n");
            }

            $printer->text("==============================\n");
            $printer->feed(1);

            // Order items
            $printer->text("ITEMS:\n");
            $printer->text("==============================\n");

            $subTotal = 0;
            $addOnsCost = 0;

            foreach ($order->details as $detail) {
                if ($detail->food_id || $detail->campaign == null) {
                    $foodDetails = json_decode($detail->food_details, true);
                    $foodName = $foodDetails['name'] ?? 'Unknown Item';

                    $printer->text($detail->quantity . "x " . $foodName . "\n");

                    // Price per item
                    $printer->text("  Price: " . Helpers::format_currency($detail->price) . "\n");

                    // Variations
                    $variations = json_decode($detail->variation, true);
                    if (count($variations) > 0) {
                        $printer->text("  Variations:\n");
                        foreach ($variations as $variation) {
                            if (isset($variation['name']) && isset($variation['values'])) {
                                $printer->text("    " . $variation['name'] . ":\n");
                                foreach ($variation['values'] as $value) {
                                    $printer->text("      - " . $value['label'] . " (" . Helpers::format_currency($value['optionPrice']) . ")\n");
                                }
                            }
                        }
                    }

                    // Add-ons
                    $addOns = json_decode($detail->add_ons, true);
                    if (count($addOns) > 0) {
                        $printer->text("  Add-ons:\n");
                        foreach ($addOns as $addon) {
                            $printer->text("    - " . $addon['name'] . " (" . $addon['quantity'] . "x" . Helpers::format_currency($addon['price']) . ")\n");
                            $addOnsCost += $addon['price'] * $addon['quantity'];
                        }
                    }

                    // Notes
                    if ($detail->notes) {
                        $printer->text("  Note: " . $detail->notes . "\n");
                    }

                    $itemTotal = $detail->price * $detail->quantity;
                    $subTotal += $itemTotal;

                    $printer->text("  Total: " . Helpers::format_currency($itemTotal) . "\n");
                    $printer->text("------------------------------\n");
                }
            }

            $printer->feed(1);
            $printer->text("==============================\n");

            // Order summary
            $printer->text("Items Price: " . Helpers::format_currency($subTotal) . "\n");
            $printer->text("Add-ons: " . Helpers::format_currency($addOnsCost) . "\n");

            $subTotalWithAddons = $subTotal + $addOnsCost;
            $printer->text("Subtotal: " . Helpers::format_currency($subTotalWithAddons) . "\n");

            if ($order->restaurant_discount_amount > 0) {
                $printer->text("Discount: -" . Helpers::format_currency($order->restaurant_discount_amount) . "\n");
            }

            if ($order->tax_status == 'excluded' || $order->tax_status == null) {
                $printer->text("Tax: " . Helpers::format_currency($order->total_tax_amount) . "\n");
            }

            $printer->text("Delivery: " . Helpers::format_currency($order->delivery_charge) . "\n");

            if ($order->additional_charge > 0) {
                $printer->text("Additional: " . Helpers::format_currency($order->additional_charge) . "\n");
            }

            $printer->text("==============================\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("TOTAL: " . Helpers::format_currency($order->order_amount) . "\n");
            $printer->text("==============================\n");

            // Payment info
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Payment: " . ucfirst(str_replace('_', ' ', $order->payment_method)) . "\n");

            if ($order->pos_details) {
                $printer->text("Cash: " . Helpers::format_currency($order->pos_details->cash_paid) . "\n");
                $printer->text("Card: " . Helpers::format_currency($order->pos_details->card_paid) . "\n");

                $change = $order->pos_details->cash_paid + $order->pos_details->card_paid - $order->pos_details->invoice_amount;
                if ($change > 0) {
                    $printer->text("Change: " . Helpers::format_currency($change) . "\n");
                }
            }

            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Thank you for your order!\n");
            $printer->text("==============================\n");

            // Feed & cut
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            // For page reload, redirect back with success message
            return redirect()->back()->with('success', 'Order printed successfully!');
        } catch (\Exception $e) {
            // For page reload, redirect back with error message
            return redirect()->back()->with('error', 'Print error: ' . $e->getMessage());
        }
    }

    public function getPrinterSettings()
    {
        $branchId = Helpers::get_restaurant_id();
        $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();

        $printers = ['bill_printer' => null, 'kitchen_printer' => null, 'orders_date' => null];
        if ($branch) {
            $printers['bill_printer'] = $branch->bill_printer ?? null;
            $printers['kitchen_printer'] = $branch->kitchen_printer ?? null;
            $printers['orders_date'] = $branch->orders_date ?? null;
        }

        return response()->json($printers);
    }

    public function savePrinterSettings(Request $request)
    {
        $bill = $request->input('billPrinter');
        $ordersDate = $request->input('ordersDate');
        $kitchen = $request->input('kitchenPrinter');
        $branchId = Helpers::get_restaurant_id();

        $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();

        if ($branch) {
            DB::table('tbl_soft_branch')
                ->where('branch_id', $branchId)
                ->update([
                    'bill_printer' => $bill,
                    'orders_date' => $ordersDate,
                    'kitchen_printer' => $kitchen,
                    'updated_at' => now()
                ]);
            return response()->json(['success' => true]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found. Please contact administrator to set up your branch first.'
            ]);
        }
    }
}
