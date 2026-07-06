@php
    use App\Models\Food;
    use App\Models\OptionsList;
    use App\Models\AddOn;
    use Illuminate\Support\Facades\DB;

    $currencyText = '(ر.ع)';
    $lrm = "\u{200E}";

    $subTotal = 0;
    $addOnsCost = 0;

    $restaurantArabicName =
        optional($order->restaurant->translations()->where('key', 'name')->where('locale', 'ar')->first())->value ??
        config('constants.invoice_restaurant_name');

    function receipt_money($amount)
    {
        return number_format((float) $amount, 3, '.', '');
    }

    function receipt_currency()
    {
        return '<span class="currency">(ر.ع)</span>';
    }
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Order #{{ $order->order_serial }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            size: 80mm 1000mm;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 80mm;
            min-height: 1000mm;
            background: #ffffff;
            color: #000000;
        }

        body {
            font-family: "Courier New", monospace;
            font-size: 9px;
            line-height: 1.3;
        }

        .receipt {
            width: 72mm;
            max-width: 72mm;
            margin: 0 auto;
            padding: 2mm;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: 600;
        }

        .large {
            font-size: 18px;
            font-weight: 600;
            line-height: 1.2;
        }

        .medium {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.25;
        }

        .arabic,
        .arabic-text,
        .item-arabic,
        .option-arabic,
        .addon-arabic,
        .note-text,
        .currency {
            font-family: "DejaVu Sans", Arial, Tahoma, sans-serif !important;
            direction: rtl;
            unicode-bidi: embed;
        }

        .currency {
            font-size: 9px;
            font-weight: normal;
            white-space: nowrap;
        }

        .arabic {
            font-size: 9px;
            font-weight: normal;
        }

        .dash {
            border-top: 1px dashed #000;
            margin: 5px 0;
            height: 1px;
            width: 100%;
        }

        .info-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin: 2px 0;
        }

        .info-row .label {
            display: table-cell;
            width: 65%;
            font-weight: 600;
            vertical-align: top;
            text-align: left;
            font-size: 9px;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .info-row .value {
            display: table-cell;
            width: 35%;
            text-align: right;
            vertical-align: top;
            font-size: 9px;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 2px 0;
            vertical-align: top;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        th {
            font-weight: 600;
            border-bottom: 1px dashed #000;
        }

        .qty {
            width: 10%;
            text-align: left;
        }

        .name {
            width: 42%;
            text-align: left;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .price {
            width: 22%;
            text-align: right;
        }

        .total {
            width: 26%;
            text-align: right;
        }

        .item-name {
            font-weight: 600;
        }

        .item-arabic {
            text-align: right;
            font-weight: 600;
            font-size: 9px;
            margin-top: 2px;
        }

        .option-line,
        .addon-line {
            padding-left: 8px;
            font-size: 9px;
            line-height: 1.25;
        }

        .option-arabic,
        .addon-arabic {
            padding-right: 8px;
            text-align: right;
            font-size: 9px;
            line-height: 1.25;
        }

        .item-summary {
            text-align: right;
            font-weight: 600;
            margin-top: 3px;
            font-size: 9px;
            line-height: 1.3;
        }

        .canceled {
            display: inline-block;
            padding: 4px 10px;
            margin: 6px 0;
            color: #ffffff;
            background: #000000;
            font-size: 16px;
            font-weight: 600;
        }

        .note-title {
            font-weight: 600;
            margin-top: 4px;
            font-size: 9px;
        }

        .note-text {
            text-align: right;
            margin: 2px 0 4px;
            font-size: 9px;
            line-height: 1.3;
        }

        table,
        tr,
        td,
        th,
        div,
        span {
            max-width: 100%;
        }

        @media print {
            html,
            body {
                margin: 0;
                padding: 0;
                width: 80mm;
            }

            .receipt {
                width: 72mm;
                max-width: 72mm;
                margin: 0 auto;
                padding: 2mm;
            }
        }
    </style>
</head>

<body>
<div class="receipt">

    {{-- Header --}}
    <div class="center">
        <div class="large arabic">{{ $restaurantArabicName }}</div>
        <div class="large">{{ $order->restaurant->name }}</div>
    </div>

    <div class="info-row">
        <div class="label">CR No. | <span class="arabic">رقم السجل التجاري</span></div>
        <div class="value">{{ config('constants.cr_no') }}</div>
    </div>

    <div class="info-row">
        <div class="label">VATIN | <span class="arabic">رقم التعريف الضريبي</span></div>
        <div class="value">{{ config('constants.vat_no') }}</div>
    </div>

    <div class="info-row">
        <div class="label">Branch Name | <span class="arabic">اسم الفرع</span></div>
        <div class="value arabic">{{ config('constants.invoice_branch_name') }}</div>
    </div>

    <div class="info-row">
        <div class="label">Phone No. | <span class="arabic">رقم الهاتف</span></div>
        <div class="value">{{ $order->restaurant->phone }}</div>
    </div>

    <div class="dash"></div>

    {{-- Order details --}}
    <div class="medium">Order # {{ $order->order_serial }}</div>

    @if ($order->order_status == 'canceled')
        <div class="center">
            <div class="canceled">CANCELED</div>
        </div>
    @endif

    @if ($order->partner_id && $order->partner)
        <div class="medium">{{ $order->partner->partner_name }}</div>
    @endif

    <br>

    <div class="info-row">
        <div class="label">Date | <span class="arabic">تاريخ</span></div>
        <div class="value">{{ date('Y-m-d H:i', strtotime($order->created_at)) }}</div>
    </div>

    <div class="info-row">
        <div class="label">Order Type | <span class="arabic">نوع الطلب</span></div>
        <div class="value">{{ ucfirst($order->order_type) }}</div>
    </div>

    {{-- Customer info --}}
    @if ($order->pos_details)
        <div class="info-row">
            <div class="label">Customer | <span class="arabic">اسم العميل</span></div>
            <div class="value arabic">{{ $order->pos_details->customer_name ?: 'Walk-in Customer' }}</div>
        </div>

        @if ($order->pos_details->phone)
            <div class="info-row">
                <div class="label">Phone | <span class="arabic">هاتف</span></div>
                <div class="value arabic">{{ $order->pos_details->phone }}</div>
            </div>
        @endif

        @if ($order->pos_details->car_number)
            <div class="info-row">
                <div class="label">Car No | <span class="arabic">رقم السيارة</span></div>
                <div class="value arabic">{{ $order->pos_details->car_number }}</div>
            </div>
        @endif
    @endif

    @if ($order->takenBy)
        <div class="info-row">
            <div class="label">Order Taker | <span class="arabic">متلقي الطلب</span></div>
            <div class="value arabic">{{ $order->takenBy->name }}</div>
        </div>
    @endif

    <div class="dash"></div>

    {{-- Items table --}}
    <table>
        <thead>
        <tr>
            <th class="qty">Qty</th>
            <th class="name">Name</th>
            <th class="price">Price</th>
            <th class="total">Total</th>
        </tr>
        <tr>
            <th class="qty arabic">الكمية</th>
            <th class="name arabic">الاسم</th>
            <th class="price arabic">السعر</th>
            <th class="total arabic">المجموع</th>
        </tr>
        </thead>

        <tbody>
        @foreach ($order->details as $detail)
            @php
                $itemAddOnsCost = 0;
            @endphp

            @if (($detail->food_id || $detail->campaign == null) && trim($detail->is_deleted) != 'Y')
                @php
                    $foodDetails = json_decode($detail->food_details, true) ?? [];
                    $foodName = $foodDetails['name'] ?? 'Unknown Item';

                    $foodArabicName = '';
                    $foodModel = Food::where('id', $detail->food_id)->first();

                    if ($foodModel) {
                        $foodArabicName = $foodModel->getTranslationValue('name', 'ar') ?? '';
                    }
                    $itemTotal = $detail->price * $detail->quantity;
                @endphp

                <tr>
                    <td class="qty">{{ $detail->quantity }}</td>
                    <td class="name item-name">{{ $foodName }}</td>
                    <td class="price">{{ receipt_money($detail->price) }}</td>
                    <td class="total">{{ receipt_money($itemTotal) }}</td>
                </tr>

                @if (!empty($foodArabicName))
                    <tr>
                        <td colspan="4" class="arabic">{{ $foodArabicName }}</td>
                    </tr>
                @endif

                {{-- Variations --}}
                @php
                    $variations = json_decode($detail->variation, true) ?? [];
                @endphp

                @if (count($variations) > 0)
                    @foreach ($variations as $variation)
                        @if (isset($variation['name']) && isset($variation['values']))
                            @foreach ($variation['values'] as $value)
                                @php
                                    $englishOptionName = '';
                                    $arabicOptionName = '';

                                    if (isset($variation['printing_option']) && $variation['printing_option'] == 'option_name') {
                                        $englishOptionName =
                                            DB::table('variation_options')
                                                ->where('id', $value['option_id'] ?? null)
                                                ->value('option_name') ?? '';
                                    } else {
                                        $englishOptionName =
                                            DB::table('options_list')
                                                ->where('id', $value['options_list_id'] ?? null)
                                                ->value('name') ?? '';
                                    }

                                    $option = OptionsList::find($value['options_list_id'] ?? null);

                                    if ($option) {
                                        $arabicOptionName = $option->getTranslationValue('name', 'ar') ?? '';
                                    }
                                @endphp

                                @if (!empty($englishOptionName) && $foodName != $englishOptionName)
                                    <tr>
                                        <td colspan="4" class="option-line">- {{ $englishOptionName }}</td>
                                    </tr>
                                @endif

                                @if (!empty($arabicOptionName))
                                    <tr>
                                        <td colspan="4" class="option-arabic">{{ $arabicOptionName }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endif

                        {{-- Variation add-ons --}}
                        @if (isset($variation['addons']) && count($variation['addons']) > 0)
                            @foreach ($variation['addons'] as $addon)
                                @php
                                    $addonQty = $addon['quantity'] ?? 1;
                                    $addonPrice = $addon['price'] ?? 0;
                                    $lineCost = $addonPrice * $addonQty;

                                    $itemAddOnsCost += $lineCost;
                                    $addOnsCost += $lineCost;

                                    $addOnArabicName = '';
                                    $addOnModel = AddOn::where('id', $addon['id'] ?? null)->first();

                                    if ($addOnModel) {
                                        $addOnArabicName = $addOnModel->getTranslationValue('name', 'ar') ?? '';
                                    }
                                @endphp

                                <tr>
                                    <td colspan="4" class="addon-line">
                                        Addon: {{ $addonQty }} x {{ $addon['name'] ?? '' }}
                                        +{{ receipt_money($addonPrice) }} {!! receipt_currency() !!}
                                    </td>
                                </tr>

                                @if ($addOnArabicName)
                                    <tr>
                                        <td colspan="4" class="addon-arabic">{{ $addOnArabicName }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                @endif

                {{-- Direct add-ons --}}
                @php
                    $addOns = json_decode($detail->add_ons, true) ?? [];
                @endphp

                @if (count($addOns) > 0)
                    <tr>
                        <td colspan="4" class="option-line bold">Add-ons:</td>
                    </tr>

                    @php
                        $addOnIds = collect($addOns)->pluck('id')->unique()->toArray();
                        $addOnModels = AddOn::whereIn('id', $addOnIds)->get()->keyBy('id');
                    @endphp

                    @foreach ($addOns as $addon)
                        @php
                            $addOnModel = $addOnModels[$addon['id']] ?? null;
                            $addOnArabicName = '';

                            if ($addOnModel) {
                                $addOnArabicName = $addOnModel->getTranslationValue('name', 'ar') ?? '';
                            }

                            $lineCost = ($addon['price'] ?? 0) * ($addon['quantity'] ?? 1);

                            $itemAddOnsCost += $lineCost;
                            $addOnsCost += $lineCost;
                        @endphp

                        <tr>
                            <td colspan="4" class="addon-line">
                                - {{ $addon['name'] ?? '' }}
                            </td>
                        </tr>

                        @if ($addOnArabicName)
                            <tr>
                                <td colspan="4" class="addon-arabic">{{ $addOnArabicName }}</td>
                            </tr>
                        @endif
                    @endforeach
                @endif

                {{-- Item notes --}}
                @if ($detail->notes)
                    <tr>
                        <td colspan="4" class="note-title">
                            Note | <span class="arabic">ملحوظة</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="note-text">{{ $detail->notes }}</td>
                    </tr>
                @endif

                @php
                    $discountAmount = $detail->discount_on_food * $detail->quantity;
                    $subTotal += $itemTotal - $discountAmount;
                    $finalItemTotal = $itemTotal + $itemAddOnsCost - $discountAmount;
                @endphp

                <tr>
                    <td colspan="4" class="item-summary">
                        @if ($detail->discount_on_food > 0)
                            Discount: -{{ receipt_money($discountAmount) }} {!! receipt_currency() !!}<br>
                        @endif

                        Addons: {{ receipt_money($itemAddOnsCost) }} {!! receipt_currency() !!}<br>
                        Total: {{ receipt_money($finalItemTotal) }} {!! receipt_currency() !!}
                    </td>
                </tr>

                <tr>
                    <td colspan="4">
                        <div class="dash"></div>
                    </td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    {{-- Order summary --}}
    @php
        $subTotalWithAddons = $subTotal + $addOnsCost;
    @endphp

    <div class="info-row">
        <div class="label">Items Price | <span class="arabic">سعر العناصر</span> {!! receipt_currency() !!}</div>
        <div class="value">{{ receipt_money($subTotal) }}</div>
    </div>

    <div class="info-row">
        <div class="label">Add-ons | <span class="arabic">الإضافات</span> {!! receipt_currency() !!}</div>
        <div class="value">{{ receipt_money($addOnsCost) }}</div>
    </div>

    <div class="info-row">
        <div class="label">Subtotal | <span class="arabic">المجموع الفرعي</span> {!! receipt_currency() !!}</div>
        <div class="value">{{ receipt_money($subTotalWithAddons) }}</div>
    </div>

    @if ($order->restaurant_discount_amount > 0)
        <div class="info-row">
            <div class="label">Discount On Bill | <span class="arabic">خصم على الفاتورة</span> {!! receipt_currency() !!}</div>
            <div class="value">{{ receipt_money($order->restaurant_discount_amount) }}</div>
        </div>
    @endif

    @if ($order->tax_status == 'excluded' || $order->tax_status == null)
        <div class="info-row">
            <div class="label">Tax | <span class="arabic">ضريبة</span> {!! receipt_currency() !!}</div>
            <div class="value">{{ receipt_money($order->total_tax_amount) }}</div>
        </div>
    @endif

    <div class="info-row">
        <div class="label">Delivery | <span class="arabic">توصيل</span> {!! receipt_currency() !!}</div>
        <div class="value">{{ receipt_money($order->delivery_charge) }}</div>
    </div>

    @if ($order->additional_charge > 0)
        <div class="info-row">
            <div class="label">Additional | <span class="arabic">إضافي</span> {!! receipt_currency() !!}</div>
            <div class="value">{{ receipt_money($order->additional_charge) }}</div>
        </div>
    @endif

    <div class="dash"></div>

    <div class="info-row medium">
        <div class="label">TOTAL | <span class="arabic">المجموع</span> {!! receipt_currency() !!}</div>
        <div class="value">{{ receipt_money($order->order_amount) }}</div>
    </div>

    <div class="dash"></div>

    {{-- Payment info --}}
    <div class="info-row">
        <div class="label">Payment Method | <span class="arabic">طريقة الدفع</span></div>
        <div class="value">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</div>
    </div>

    @if ($order->pos_details)
        <div class="info-row">
            <div class="label">Cash | <span class="arabic">نقدي</span> {!! receipt_currency() !!}</div>
            <div class="value">{{ receipt_money($order->pos_details->cash_paid) }}</div>
        </div>

        <div class="info-row">
            <div class="label">Card | <span class="arabic">بطاقة</span> {!! receipt_currency() !!}</div>
            <div class="value">{{ receipt_money($order->pos_details->card_paid) }}</div>
        </div>

        @php
            $change =
                $order->pos_details->cash_paid +
                $order->pos_details->card_paid -
                $order->pos_details->invoice_amount;
        @endphp

        @if ($change > 0)
            <div class="info-row">
                <div class="label">Change | <span class="arabic">يتغير</span> {!! receipt_currency() !!}</div>
                <div class="value">{{ receipt_money($change) }}</div>
            </div>
        @endif
    @endif

    {{-- Order note --}}
    @if ($order->order_note)
        <div class="note-title">
            Order Note | <span class="arabic">ملاحظة الطلب</span>
        </div>

        <div class="note-text">
            {{ $order->order_note }}
        </div>

        <div class="dash"></div>
    @endif

    {{-- Footer --}}
    <br>

    <div class="center bold">
        Thank you for your order!
    </div>

    <div class="center arabic bold">
        شكراً لطلبك
    </div>

    <div class="dash"></div>

</div>
</body>
</html>