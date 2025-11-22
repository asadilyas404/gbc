@php
    // Helper function to shape Arabic text properly
    function shapeArabic($text) {
        if (empty($text) || !is_string($text)) {
            return $text;
        }
        try {
            // Try new namespace first
            if (class_exists('\ArPHP\I18N\Arabic')) {
                $arabic = new \ArPHP\I18N\Arabic('Glyphs');
                return $arabic->utf8Glyphs($text);
            }
            // Fallback to old class name
            if (class_exists('I18N_Arabic')) {
                $arabic = new \I18N_Arabic('Glyphs');
                return $arabic->utf8Glyphs($text);
            }
        } catch (\Exception $e) {
            // Fallback if Arabic library is not available
        }
        return $text;
    }
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Invoice - {{ $order->order_serial }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            background: #fff;
            padding: 20px;
        }

        /* Arabic text styling */
        [dir="rtl"], .rtl {
            direction: rtl;
            unicode-bidi: embed;
            font-family: 'DejaVu Sans', 'Arial Unicode MS', 'Tahoma', sans-serif;
        }


        .invoice-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .header .restaurant-name {
            font-size: 18px;
            font-weight: bold;
            color: #34495e;
            margin-bottom: 5px;
        }

        .header .restaurant-info {
            font-size: 11px;
            color: #7f8c8d;
            margin: 3px 0;
        }

        .order-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .order-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .order-info-row:last-child {
            margin-bottom: 0;
        }

        .order-info-label {
            font-weight: bold;
            color: #555;
        }

        .order-info-value {
            color: #2c3e50;
        }

        .order-id {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
        }

        .customer-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .customer-section h3 {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead {
            background: #34495e;
            color: #fff;
        }

        .items-table th {
            padding: 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .item-name {
            font-weight: bold;
            color: #2c3e50;
        }

        .item-details {
            font-size: 10px;
            color: #7f8c8d;
            margin-top: 3px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            border-top: 2px solid #34495e;
            padding-top: 10px;
            margin-top: 10px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
        }

    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="restaurant-name">{{ $order->restaurant->name }}</div>
            @if($order->restaurant->address)
            <div class="restaurant-info">{{ $order->restaurant->address }}</div>
            @endif
            @if($order->restaurant->phone)
            <div class="restaurant-info">Phone: {{ $order->restaurant->phone }}</div>
            @endif
        </div>

        <!-- Order Information -->
        <div class="order-info">
            <div class="order-info-row">
                <span class="order-info-label">Order ID:</span>
                <span class="order-info-value order-id">#{{ $order->order_serial }}</span>
            </div>
            <div class="order-info-row">
                <span class="order-info-label">Date:</span>
                <span class="order-info-value">{{ \Carbon\Carbon::parse($order->created_at)->format('d M Y, h:i A') }}</span>
            </div>
            <div class="order-info-row">
                <span class="order-info-label">Order Type:</span>
                <span class="order-info-value">{{ ucfirst(str_replace('_', ' ', $order->order_type)) }}</span>
            </div>
        </div>

        <!-- Customer Information -->
        @if($order->customer || $order->pos_details || $order->delivery_address)
        <div class="customer-section">
            <h3>Customer Information</h3>
            @if($order->customer)
                <div class="order-info-row">
                    <span class="order-info-label">Name:</span>
                    <span class="order-info-value">{{ $order->customer->f_name }} {{ $order->customer->l_name }}</span>
                </div>
                @if($order->customer->phone)
                <div class="order-info-row">
                    <span class="order-info-label">Phone:</span>
                    <span class="order-info-value">{{ $order->customer->phone }}</span>
                </div>
                @endif
            @elseif($order->pos_details)
                <div class="order-info-row">
                    <span class="order-info-label">Name:</span>
                    <span class="order-info-value">{{ $order->pos_details->customer_name ?? 'Walk-in Customer' }}</span>
                </div>
                @if($order->pos_details->phone)
                <div class="order-info-row">
                    <span class="order-info-label">Phone:</span>
                    <span class="order-info-value">{{ $order->pos_details->phone }}</span>
                </div>
                @endif
            @endif

            @if($order->delivery_address)
                @php $address = json_decode($order->delivery_address, true); @endphp
                @if(isset($address['address']))
                <div class="order-info-row">
                    <span class="order-info-label">Delivery Address:</span>
                    <span class="order-info-value">{{ $address['address'] }}</span>
                </div>
                @endif
            @endif
        </div>
        @endif

        <!-- Order Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $sub_total = 0;
                    $add_ons_cost = 0;
                @endphp
                @foreach ($order->details as $detail)
                    @if ($detail->food_id || $detail->campaign == null)
                        @php
                            $foodDetails = json_decode($detail->food_details, true);
                            $food = \App\Models\Food::where(['id' => $foodDetails['id'] ?? null])->first();
                            $variations = json_decode($detail->variation, true) ?? [];
                            $addOns = json_decode($detail->add_ons, true) ?? [];

                            // Calculate addons cost for this item
                            $itemAddonsCost = 0;
                            foreach($addOns as $addon) {
                                $itemAddonsCost += ($addon['price'] ?? 0) * ($addon['quantity'] ?? 1);
                            }

                            // Calculate variation addons cost
                            foreach($variations as $variation) {
                                if(isset($variation['addons'])) {
                                    foreach($variation['addons'] as $addon) {
                                        $itemAddonsCost += ($addon['price'] ?? 0) * ($addon['quantity'] ?? 1);
                                    }
                                }
                            }

                            $itemTotal = ($detail->price * $detail->quantity) + $itemAddonsCost - ($detail->discount_on_food ?? 0);
                            $sub_total += $itemTotal;
                            $add_ons_cost += $itemAddonsCost;
                        @endphp
                        <tr>
                            <td>
                                <div class="item-name">
                                    {{ $foodDetails['name'] ?? 'Unknown Item' }}
                                    @if($food)
                                        @php $arabicName = $food->getTranslationValue('name', 'ar'); @endphp
                                        @if($arabicName)
                                            <br><span style="font-size: 10px; color: #666; direction: rtl; unicode-bidi: embed;">{{ shapeArabic($arabicName) }}</span>
                                        @endif
                                    @endif
                                </div>
                                @if(count($variations) > 0 || count($addOns) > 0)
                                <div class="item-details">
                                    @if(count($variations) > 0)
                                        <strong>{{ translate('messages.variation') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('تفاوت') }}</span>:</strong>
                                        @foreach($variations as $variation)
                                            @if(isset($variation['values']))
                                                @foreach($variation['values'] as $value)
                                                    @php
                                                        $optionList = \App\Models\OptionsList::find($value['options_list_id'] ?? null);
                                                    @endphp
                                                    {{ $value['label'] ?? ($optionList ? $optionList->name : '') }}
                                                    @if($optionList)
                                                        @php $arabicOptionName = $optionList->getTranslationValue('name', 'ar'); @endphp
                                                        @if($arabicOptionName)
                                                            <span style="font-size: 10px; color: #666; direction: rtl; unicode-bidi: embed;">({{ shapeArabic($arabicOptionName) }})</span>
                                                        @endif
                                                    @endif
                                                    @if(!$loop->last), @endif
                                                @endforeach
                                            @endif
                                        @endforeach
                                    @endif
                                    @if(count($addOns) > 0)
                                        @if(count($variations) > 0) | @endif
                                        <strong>{{ translate('messages.addons') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('الإضافات') }}</span>:</strong>
                                        @foreach($addOns as $addon)
                                            @php
                                                $addonModel = \App\Models\AddOn::find($addon['id'] ?? null);
                                            @endphp
                                            {{ $addon['name'] }}
                                            @if($addonModel)
                                                @php $arabicAddonName = $addonModel->getTranslationValue('name', 'ar'); @endphp
                                                @if($arabicAddonName)
                                                    <span style="font-size: 10px; color: #666; direction: rtl; unicode-bidi: embed;">({{ shapeArabic($arabicAddonName) }})</span>
                                                @endif
                                            @endif
                                            @if(!$loop->last), @endif
                                        @endforeach
                                    @endif
                                </div>
                                @endif
                                @if($detail->notes)
                                <div class="item-details" style="font-style: italic; color: #555;">
                                    {{ translate('messages.note') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('ملاحظة') }}</span>: {{ $detail->notes }}
                                </div>
                                @endif
                            </td>
                            <td class="text-right">{{ $detail->quantity }}</td>
                            <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($detail->price) }}</td>
                            <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($itemTotal) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span>{{ translate('messages.subtotal') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('المجموع الفرعي') }}</span>:</span>
                <span>{{ \App\CentralLogics\Helpers::format_currency($sub_total) }}</span>
            </div>
            @if($order->restaurant_discount_amount > 0)
            <div class="total-row">
                <span>{{ translate('messages.discount') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('تخفيض') }}</span>:</span>
                <span>-{{ \App\CentralLogics\Helpers::format_currency($order->restaurant_discount_amount) }}</span>
            </div>
            @endif
            @if($order->total_tax_amount > 0)
            <div class="total-row">
                <span>{{ translate('messages.tax') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('ضريبة') }}</span>:</span>
                <span>{{ \App\CentralLogics\Helpers::format_currency($order->total_tax_amount) }}</span>
            </div>
            @endif
            @if($order->delivery_charge > 0)
            <div class="total-row">
                <span>{{ translate('messages.delivery_charge') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('رسوم التوصيل') }}</span>:</span>
                <span>{{ \App\CentralLogics\Helpers::format_currency($order->delivery_charge) }}</span>
            </div>
            @endif
            @if($order->additional_charge > 0)
            <div class="total-row">
                <span>{{ translate('messages.additional_charge') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('رسوم إضافية') }}</span>:</span>
                <span>{{ \App\CentralLogics\Helpers::format_currency($order->additional_charge) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>{{ translate('messages.total_amount') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('المبلغ الإجمالي') }}</span>:</span>
                <span>{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
            </div>
            <div class="total-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <span>{{ translate('messages.payment_method') }} | <span style="direction: rtl; unicode-bidi: embed;">{{ shapeArabic('طريقة الدفع') }}</span>:</span>
                <span>{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your order!</p>
            <p>For any inquiries, please contact us at {{ $order->restaurant->phone ?? 'N/A' }}</p>
        </div>
    </div>
</body>
</html>

