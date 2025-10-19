<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use App\Models\Order;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Auth;

use Intervention\Image\ImageManager;
use Mike42\Escpos\EscposImage;
use I18N_Arabic;





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
        function formatRowOld(array $columns, array $widths, array $aligns = [])
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

        // try {
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


        //image print

        // Arabic text
        $arabicText = "ر.ع"; // "مرحبا بكم في مالك البيتزا"; // "Welcome to Malek Pizza"

        // 🔹 Create Arabic PNG
//     $filePath = $this->createArabicImage($arabicText);
//   $logo = \Mike42\Escpos\EscposImage::load($filePath, false);
//   $printer->setEmphasis(true);

        // Set double height and double width (bigger font)
        // $printer->graphics($logo);

        // // Load image and send to printer
// $escposImg = \Mike42\Escpos\EscposImage::load($imagePath, false);
// $printer->bitImage($escposImg);


        // $img = EscposImage::load($imagePath, false);
// $printer->bitImage($img);

        // End of image print
        $linedash = "------------------------------------------------\n";
        // Print order header
        $printer->setEmphasis(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $printer->setTextSize(2, 2);
        $printer->text($order->restaurant->name . "\n");

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
        // $printer->feed(1);

        // Order items
        //  $printer->text("Qty" . "   " . "Item" . str_repeat(" ", 32)  . "Amount\n");

        // Define column widths (adjust for your printer, usually 42 for 80mm)
        $colWidths = [5, 30, 12];           // Qty, Name, Amount
        $colAligns = ['left', 'left', 'right'];  // Amount right-aligned

        // $colArabic= formatRow(["كمية",  "اسم", "الكمية"], $colWidths, $colAligns);

        // $bmpFile = $this->createArabicPngTight($colArabic, 'arabic-text.png');
// $image = \Mike42\Escpos\EscposImage::load($bmpFile);

        //$printer->graphics($image);


        // Items
        $printer->text(formatRow(["Qty", "Name", "Price"], $colWidths, $colAligns) . "\n");
        //$printer->text(formatRow(["1", "Pizza Margherita", "2.500"], $colWidths, $colAligns) . "\n");

        $printer->text($linedash);

        $subTotal = 0;
        $addOnsCost = 0;

        foreach ($order->details as $detail) {
            if ($detail->food_id || $detail->campaign == null) {
                $foodDetails = json_decode($detail->food_details, true);

                $foodName = $foodDetails['name'] ?? 'Unknown Item';

                //  $printer->text($detail->quantity . "x " . $foodName . "\n");

                // Price per item
                // $printer->text("  Price: " . Helpers::format_currency($detail->price) . "\n");
                //  $printer->text("  G Price: " . $detail->price . "\n" );

                $printer->setEmphasis(true);
                $printer->text(formatRow([$detail->quantity, $foodName, number_format($detail->price, 3, '.', '')], $colWidths, $colAligns) . "\n");

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
                            $printer->text("  " . $variation['name'] . ":");
                            foreach ($variation['values'] as $value) {
                                //$printer->text(" - " . $value['label'] . " (" . Helpers::format_currency($value['optionPrice']) . ")\n");
                                $printer->text(" - " . $value['label'] . " (" . number_format($value['optionPrice'], 3, '.', '') . ")\n");
                            }
                        }

                        //variation addon

                        // Print addons if available
                        if (isset($variation['addons']) && count($variation['addons']) > 0) {
                            foreach ($variation['addons'] as $addon) {
                                $printer->text("     V Addon: " . $addon['name']
                                    . " | (" . number_format($addon['price'], 3, '.', '')
                                    . " x " . $addon['quantity'] . ")\n");

                                $addOnsCost += $addon['price'] * $addon['quantity'];
                            }
                        }
                    }
                }


                $printer->setEmphasis(false);
                // Add-ons
                $addOns = json_decode($detail->add_ons, true);
                if (count($addOns) > 0) {
                    $printer->text("  Add-ons:");
                    foreach ($addOns as $addon) {
                        // $printer->text("    - " . $addon['name'] . " (" . $addon['quantity'] . "x" . Helpers::format_currency($addon['price']) . ")\n");
                        $printer->text("-" . $addon['name'] . " (" . $addon['quantity'] . "x" . number_format($addon['price'], 3, '.', '') . ")\n");

                        $addOnsCost += $addon['price'] * $addon['quantity'];
                    }
                }

                // Notes
                if ($detail->notes) {
                    $printer->text("  Note: " . $detail->notes . "\n");
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

        //$printer->feed(1);
        //$printer->text($linedash);

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

        $printer->text($linedash);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("TOTAL: " . Helpers::format_currency($order->order_amount) . "\n");
        $printer->text($linedash);

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
        $printer->text($linedash);

        // Feed & cut
        $printer->feed(2);
        $printer->cut();
        $printer->close();

        return redirect()->back()->with('success', 'Order printed successfully!');
        // } catch (\Exception $e) {
        //     // For page reload, redirect back with error message
        //     return redirect()->back()->with('error', 'Print error: ' . $e->getMessage());
        // }
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

    public function printOrderKitchen(Request $request)
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
