<div class="content container-fluid initial-38 new-invoice ">
    <div class="row justify-content-center" id="printableArea">
        <div class="col-md-12">
            <div class="text-center">
                <input type="button" class="btn text-white btn--primary non-printable print-Div"
                    value="{{ translate('messages.Proceed_If_thermal_printer_is_ready.') }}" />
                <a href="{{ url()->previous() }}"
                    class="btn btn-danger non-printable">{{ translate('messages.back') }}</a>
            </div>
            <hr class="non-printable">

            <div class="initial-38-1">
                <div class="pt-3">
                    <img src="{{ dynamicAsset('/public/assets/admin/img/restaurant-invoice.png') }}"
                        class="initial-38-2" alt="">
                </div>
                <div class="text-center pt-3 mb-3">
                    <h5 class="initial-38-3">{{ $order->restaurant->name }}</h5>
                    <h5 class="initial-38-3">مالك البيتزا </h5>
                    <h5 class="text-break initial-38-4">
                        {{ $order->restaurant->address }}
                    </h5>
                    <h5 class="text-break initial-38-4">
                        المصنعة
                    </h5>
                    <h5 class="text-muted">
                        {{ Carbon\Carbon::parse($order['created_at'])->locale(app()->getLocale())->translatedFormat('d/M/Y ' . config('timeformat')) }}
                    </h5>
                    <h5>
                        <span>{{ translate('phone') }} | هاتف</span> <span>:</span>
                        <span>{{ $order->restaurant->phone }}</span>
                    </h5>
                    @if ($order->restaurant->gst_status)
                        <h5 class="initial-38-4 initial-38-3 fz-12px text-center">
                            <span>{{ translate('Gst_No') }} | رقم ضريبة السلع والخدمات</span> <span>:</span>
                            <span>{{ $order->restaurant->gst_code }}</span>
                        </h5>
                    @endif
                    {{-- <span class="text-center">Gst: {{$order->restaurant->gst_code}}</span> --}}
                </div>

                <h5 class="d-flex justify-content-between gap-2">
                    <span class=""> {{ translate('Order_Type') }} | نوع الطلب</span>
                    <span class="">
                        {{ $order->order_type == 'delivery'
                            ? translate('Home_Delivery') . ' | ' . 'توصيل الطلبات للمنازل'
                            : translate($order->order_type) }}
                    </span>

                </h5>

                <div class="border border-dashed border-secondary p-3 rounded">
                    <h5 class="d-flex justify-content-between gap-2">
                        <span class="text-muted"> {{ translate('Order_ID') }} | معرف الطلب</span>
                        <span class="">{{ $order['order_serial'] }}</span>
                    </h5>

                    @if ($order->delivery_address)
                        <h5 class="d-flex justify-content-between gap-2">
                            <span class="text-muted">{{ translate('Customer_Name') }}</span>
                            <span>
                                {{ isset($order->delivery_address) ? json_decode($order->delivery_address, true)['contact_person_name'] : '' }}
                            </span>
                        </h5>
                        <h5>
                            اسم العميل
                        </h5>
                        <h5 class="d-flex justify-content-between gap-2">
                            <span class="text-muted">{{ translate('messages.phone') }} | هاتف</span>
                            <span>
                                {{ isset($order->delivery_address) ? json_decode($order->delivery_address, true)['contact_person_number'] : '' }}
                            </span>
                        </h5>

                        <h5 class="d-flex justify-content-between gap-2 text-break">
                            <span class="text-muted text-nowrap">{{ translate('messages.delivery_Address') }}</span>
                            <span class="text-right">
                                {{ isset($order->delivery_address) ? json_decode($order->delivery_address, true)['address'] : '' }}
                            </span>
                        </h5>

                        <div class="d-flex gap-2 align-items-center justify-content-end" style="font-size: 10px">
                            @if (isset($order->delivery_address) && isset(json_decode($order->delivery_address, true)['road']))
                                <div class="d-flex gap-1">
                                    <span class="text-muted">{{ translate('messages.street_No') }}</span>:
                                    <span>
                                        {{ json_decode($order->delivery_address, true)['road'] }}
                                    </span>
                                </div>
                            @endif

                            @if (isset($order->delivery_address) && isset(json_decode($order->delivery_address, true)['house']))
                                <div class="d-flex gap-1">
                                    <span class="text-muted">{{ translate('messages.House') }}</span>:
                                    <span class="font-light">
                                        {{ json_decode($order->delivery_address, true)['house'] }}
                                    </span>
                                </div>
                            @endif

                            @if (isset($order->delivery_address) && isset(json_decode($order->delivery_address, true)['floor']))
                                <div class="d-flex gap-1">
                                    <span class="text-muted">{{ translate('messages.floor') }}</span>:
                                    <span class="font-light">
                                        {{ json_decode($order->delivery_address, true)['floor'] }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @else
                        <h5 class="d-flex justify-content-between gap-2">
                            {{ translate('Customer_Name') }} :
                            <span class="font-light">
                                {{ translate('messages.walk_in_customer') }}
                            </span>
                        </h5>
                        <h5>
                            اسم العميل
                        </h5>
                        @if ($order->pos_details->customer_name)
                            <h5 class="d-flex justify-content-between gap-2">
                                {{ translate('Order_By') }} :
                                <span class="font-light">
                                    {{ $order->pos_details->customer_name }}
                                </span>
                            </h5>
                            <h5>
                                اطلب بواسطة
                            </h5>
                        @endif
                        @if ($order->pos_details->car_number)
                            <h5 class="d-flex justify-content-between gap-2">
                                {{ translate('Car_No') }} :
                                <span class="font-light">
                                    {{ $order->pos_details->car_number }}
                                </span>
                            </h5>
                            <h5>
                                رقم السيارة
                            </h5>
                        @endif
                        @if ($order->pos_details->phone)
                            <h5 class="d-flex justify-content-between gap-2">
                                {{ translate('messages.phone') }} :
                                <span class="font-light">
                                    {{ $order->pos_details->phone }}
                                </span>
                            </h5>
                            <h5>
                                هاتف
                            </h5>
                        @endif
                        @if ($maxMakeTime)
                            <h5 class="d-flex justify-content-between gap-2">
                                Estimated Make Time :
                                <span class="font-light">
                                    {{ $maxMakeTime }} minutes
                                </span>
                            </h5>
                            <h5>
                                الوقت المقدر للتصنيع
                            </h5>
                        @endif
                    @endif
                </div>

                <table class="table table-borderless table-align-middle mt-1 mb-1">
                    <thead>
                        <tr>
                            <th style="border-bottom: 1px dashed #979797 !important;">{{ translate('messages.QTY') }} |
                                كمية
                            </th>
                            <th style="border-bottom: 1px dashed #979797 !important;">{{ translate('messages.item') }}
                                | غرض
                            </th>
                            <th class="text-right" style="border-bottom: 1px dashed #979797 !important;">
                                {{ translate('messages.price') }} | سعر</th>
                        </tr>
                    </thead>

                    <tbody>
                        @php($sub_total = 0)
                        @php($total_tax = 0)
                        @php($total_dis_on_pro = 0)
                        @php($add_ons_cost = 0)
                        @foreach ($order->details as $detail)
                            @if ($detail->food_id || $detail->campaign == null)
                                @php($food = \App\Models\Food::where(['id' => json_decode($detail->food_details, true)['id']])->first())
                                <tr>
                                    <td class="">
                                        {{ $detail['quantity'] }}x
                                    </td>
                                    <td class="text-break">
                                        {{ json_decode($detail->food_details, true)['name'] }} <br>
                                        {{ $food->getTranslationValue('name', 'ar') }}
                                        <br>

                                        @if (count(json_decode($detail['variation'], true)) > 0)
                                            <strong>{{ translate('messages.variation') }} | تفاوت : </strong>
                                            @foreach (json_decode($detail['variation'], true) as $variation)
                                                @if (isset($variation['name']) && isset($variation['values']))
                                                    <span class="d-block text-capitalize">
                                                        <strong>{{ $variation['name'] }} - </strong>
                                                    </span>
                                                    @foreach ($variation['values'] as $value)
                                                        <span class="d-block text-capitalize">
                                                            &nbsp; &nbsp; {{ $value['label'] }} :
                                                            <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                        </span>
                                                    @endforeach
                                                @else
                                                    @if (isset(json_decode($detail['variation'], true)[0]))
                                                        @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                            <div class="font-size-sm text-body">
                                                                <span>{{ $key1 }} : </span>
                                                                <span
                                                                    class="font-weight-bold">{{ $variation }}</span>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                    @break
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="font-size-sm text-body">
                                                <span>{{ translate('messages.Price') }} | سعر : </span>
                                                <span
                                                    class="font-weight-bold">{{ \App\CentralLogics\Helpers::format_currency($detail->price) }}</span>
                                            </div>
                                        @endif

                                        @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                            @if ($key2 == 0)
                                                <strong><u>{{ translate('messages.addons') }} | الإضافات :
                                                    </u></strong>
                                            @endif
                                            <div class="font-size-sm text-body">
                                                <span class="text-break">{{ $addon['name'] }} : </span>
                                                <span class="font-weight-bold">
                                                    {{ $addon['quantity'] }} x
                                                    {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                </span>
                                            </div>
                                            @php($add_ons_cost += $addon['price'] * $addon['quantity'])
                                        @endforeach
                                    </td>
                                    <td class="text-right w-28p">
                                        @php($amount = $detail['price'] * $detail['quantity'])
                                        {{ \App\CentralLogics\Helpers::format_currency($amount) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3">
                                        Note | ملحوظة: {{ $detail['notes'] }}
                                    </td>
                                </tr>
                                @php($sub_total += $amount)
                                @php($total_tax += $detail['tax_amount'] * $detail['quantity'])
                            @elseif($detail->campaign)
                                <tr>
                                    <td class="">
                                        {{ $detail['quantity'] }}
                                    </td>
                                    <td class="">
                                        {{ $detail->campaign['title'] }} <br>
                                        @if (count(json_decode($detail['variation'], true)) > 0)
                                            <strong><u>{{ translate('messages.variation') }} | تفاوت : </u></strong>
                                            @foreach (json_decode($detail['variation'], true) as $variation)
                                                @if (isset($variation['name']) && isset($variation['values']))
                                                    <span class="d-block text-capitalize">
                                                        <strong>{{ $variation['name'] }} - </strong>
                                                    </span>
                                                    @foreach ($variation['values'] as $value)
                                                        <span class="d-block text-capitalize">
                                                            &nbsp; &nbsp; {{ $value['label'] }} :
                                                            <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                        </span>
                                                    @endforeach
                                                @else
                                                    @if (isset(json_decode($detail['variation'], true)[0]))
                                                        @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                            <div class="font-size-sm text-body">
                                                                <span>{{ $key1 }} : </span>
                                                                <span
                                                                    class="font-weight-bold">{{ $variation }}</span>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                    @break
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="font-size-sm text-body">
                                                <span>{{ translate('messages.Price') }} | سعر : </span>
                                                <span
                                                    class="font-weight-bold">{{ \App\CentralLogics\Helpers::format_currency($detail->price) }}</span>
                                            </div>
                                        @endif

                                        @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                            @if ($key2 == 0)
                                                <strong><u>{{ translate('messages.addons') }} | الإضافات :
                                                    </u></strong>
                                            @endif
                                            <div class="font-size-sm text-body">
                                                <span>{{ $addon['name'] }} : </span>
                                                <span class="font-weight-bold">
                                                    {{ $addon['quantity'] }} x
                                                    {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                </span>
                                            </div>
                                            @php($add_ons_cost += $addon['price'] * $addon['quantity'])
                                        @endforeach
                                    </td>
                                    <td class="w-28p">
                                        @php($amount = $detail['price'] * $detail['quantity'])
                                        {{ \App\CentralLogics\Helpers::format_currency($amount) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3">
                                        Note | ملحوظة: {{ $detail['notes'] }}
                                    </td>
                                </tr>
                                @php($sub_total += $amount)
                                @php($total_tax += $detail['tax_amount'] * $detail['quantity'])
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="border-bottom-dashed mb-3"></div>
                <div class="initial-38-9">
                    <div class="px-3">
                        <dl class="row text-right">
                            <dt class="col-6 text-left text-muted">{{ translate('Items_Price') }} | سعر العناصر</dt>
                            <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($sub_total) }}</dd>
                            <dt class="col-6 text-left text-muted">{{ translate('Addon_Cost') }} | تكلفة الملحق</dt>
                            <dd class="col-6">
                                {{ \App\CentralLogics\Helpers::format_currency($add_ons_cost) }}
                            </dd>

                            <dd class="col-12 border-bottom-dashed mb-2"></dd>

                            <dt class="col-6 text-left fw-500">{{ translate('messages.subtotal') }} | المجموع الفرعي
                                @if ($order->tax_status == 'included')
                                    ({{ translate('messages.TAX_Included') }}) | شامل الضريبة
                                @endif
                            </dt>
                            <dd class="col-6 fw-500">
                                {{ \App\CentralLogics\Helpers::format_currency($sub_total + $add_ons_cost) }}</dd>

                            <dd class="col-12 border-bottom-dashed mb-2"></dd>

                            <dt class="col-6 text-left text-muted">{{ translate('messages.discount') }} | تخفيض</dt>
                            <dd class="col-6">
                                -
                                {{ \App\CentralLogics\Helpers::format_currency($order['restaurant_discount_amount']) }}
                            </dd>

                            <dt class="col-6 text-left text-muted">{{ translate('messages.coupon_discount') }} | خصم
                                قسيمة</dt>
                            <dd class="col-6">
                                - {{ \App\CentralLogics\Helpers::format_currency($order['coupon_discount_amount']) }}
                            </dd>

                            @if ($order['ref_bonus_amount'] > 0)
                                <dt class="col-6  text-left text-muted">{{ translate('messages.Referral_Discount') }}
                                    |
                                    خصم الإحالة:
                                </dt>
                                <dd class="col-6">
                                    - {{ \App\CentralLogics\Helpers::format_currency($order['ref_bonus_amount']) }}
                                </dd>
                            @endif


                            @if ($order->tax_status == 'excluded' || $order->tax_status == null)
                                <dt class="col-6 text-left text-muted">{{ translate('messages.vat/tax') }}</dt>
                                <dd class="col-6">
                                    {{ \App\CentralLogics\Helpers::format_currency($order['total_tax_amount']) }}
                                </dd>
                            @endif
                            <dt class="col-6 text-left text-muted">{{ translate('messages.delivery_man_tips') }}</dt>
                            <dd class="col-6">
                                @php($dm_tips = $order['dm_tips'])
                                {{ \App\CentralLogics\Helpers::format_currency($dm_tips) }}
                            </dd>
                            <dt class="col-6 text-left text-muted">{{ translate('messages.delivery_charge') }} | رسوم
                                التسليم</dt>
                            <dd class="col-6">
                                @php($del_c = $order['delivery_charge'])
                                {{ \App\CentralLogics\Helpers::format_currency($del_c) }}

                                @if (\App\CentralLogics\Helpers::get_business_data('additional_charge_status') == 1 || $order['additional_charge'] > 0)
                                    @php($additional_charge_status = 1)
                                @else
                                    @php($additional_charge_status = 0)
                                    <hr>
                                @endif
                            </dd>
                            @if ($additional_charge_status)
                                <dt class="col-6 text-left text-muted">
                                    {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('messages.additional_charge') }}
                                    | رسوم إضافية:
                                </dt>
                                <dd class="col-6">
                                    + {{ \App\CentralLogics\Helpers::format_currency($order['additional_charge']) }}
                                </dd>
                            @endif


                            @if ($order['extra_packaging_amount'] > 0)
                                <dt class="col-6  text-left text-muted">
                                    {{ translate('messages.Extra_Packaging_Amount') }}:</dt>
                                <dd class="col-6">
                                    +
                                    {{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}
                                </dd>
                            @endif

                            <dd class="col-12 border-bottom-dashed mb-2"></dd>

                            <dt class="col-6 text-left fw-500 fz-20px">{{ translate('messages.total') }} | المجموع
                            </dt>
                            <dd class="col-6 fz-20px fw-500">
                                {{ \App\CentralLogics\Helpers::format_currency($order['order_amount']) }}
                            </dd>

                            @if ($order->payments)
                                @foreach ($order->payments as $payment)
                                    @if ($payment->payment_status == 'paid')
                                        @if ($payment->payment_method == 'cash_on_delivery')
                                            <dt class="col-6 text-left text-muted">
                                                {{ translate('messages.Paid_with_Cash') }} | تدفع نقدا
                                                ({{ translate('COD') }})
                                            </dt>
                                        @else
                                            <dt class="col-6 text-left text-muted">
                                                {{ translate('messages.Paid_by') }}
                                                | تدفع بواسطة
                                                {{ translate($payment->payment_method) }} </dt>
                                        @endif
                                    @else
                                        <dt class="col-6 text-left text-muted">{{ translate('Due_Amount') }}
                                            ({{ $payment->payment_method == 'cash_on_delivery' ? translate('messages.COD') : translate($payment->payment_method) }})
                                            :</dt>
                                    @endif
                                    <dd class="col-6 ">
                                        {{ \App\CentralLogics\Helpers::format_currency($payment->amount) }}
                                    </dd>
                                @endforeach
                            @endif
                        </dl>
                    </div>
                </div>

                <dd class="col-12 border-bottom-dashed mb-2"></dd>

                <div class="d-flex flex-row justify-content-between">
                    <span class="text-capitalize d-flex"><span>{{ translate('Paid_by') }} | تدفع بواسطة</span>
                        <span>:</span>
                        <span>{{ translate(str_replace('_', ' ', $order['payment_method'])) }}</span> </span>
                    @if ($order->adjusment > $order->order_amount)
                        <span>{{ translate('messages.amount') }} | كمية : {{ $order->adjusment }}</span>
                        <span>{{ translate('messages.change') }} | يتغير :
                            {{ $order->adjusment - $order->order_amount }}</span>
                    @endif
                </div>

                <div class="d-flex flex-row justify-content-between">
                    <span>{{ translate('messages.Cash') }} | نقدي :
                        {{ \App\CentralLogics\Helpers::format_currency($order->pos_details->cash_paid) }}</span>
                    <span>{{ translate('messages.Card') }} بطاقة :
                        {{ \App\CentralLogics\Helpers::format_currency($order->pos_details->card_paid) }}</span>
                </div>

                <div class="d-flex flex-row justify-content-between">
                    @if ($order->pos_details->invoice_amount < $order->pos_details->cash_paid + $order->pos_details->card_paid)
                        <span>{{ translate('messages.change') }} | يتغير :
                            {{ \App\CentralLogics\Helpers::format_currency($order->pos_details->cash_paid + $order->pos_details->card_paid - $order->pos_details->invoice_amount) }}</span>
                    @endif
                </div>

                <dd class="col-12 border-bottom-dashed my-2"></dd>

                <h5 class="text-center pt-1  justify-content-center mb-0">
                    <span class="d-block fw-500">{{ translate('messages.THANK_YOU') }} | شكرًا لك</span>
                </h5>
                <div class="text-center">{{ translate('for_ordering_food_from') }}
                    {{ \App\Models\BusinessSetting::where(['key' => 'business_name'])->first()->value }}</div>
                <div class="text-center">لطلب الطعام من مالك البيتزا </div>

                <dd class="col-12 border-bottom-dashed my-2"></dd>

                <span class="d-block text-center">© {{ date('Y') }}
                    {{ \App\Models\BusinessSetting::where(['key' => 'business_name'])->first()->value }}.
                    {{ translate('messages.all_right_reserved') }}</span>
            </div>
        </div>
    </div>
</div>
