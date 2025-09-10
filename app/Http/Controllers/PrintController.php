<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use App\Models\Order;
use App\CentralLogics\Helpers;

class PrintController extends Controller
{
    public function printOrder(Request $request)
    {
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

            $branchId = Helpers::get_restaurant_id();
            dd($branchId);
            $branch = DB::table('tbl_soft_branch')->where('branch_id', $branchId)->first();
            $printerName = $branch->bill_printer ?? 'BillPrinter';
dd($printerName);
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
            $printer->text("Date: " . $order->created_at->format('d/M/Y H:i') . "\n");
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

            return redirect()->back()->with('success', 'Order printed successfully!');
        // } catch (\Exception $e) {
        //     // For page reload, redirect back with error message
        //     return redirect()->back()->with('error', 'Print error: ' . $e->getMessage());
        // }
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
            $branchId = Helpers::get_restaurant_id();
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
            $printer->text("Date: " . $order->created_at->format('d/M/Y H:i') . "\n");
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
