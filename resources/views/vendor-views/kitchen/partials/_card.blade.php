@php
    $total_variation_addon_price = 0;
@endphp
<div class="col-12 col-md-6 col-lg-4 mb-2" id="order_{{ $order->id }}">
    <div class="card" id="{{ $order->kitchen_status }}">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <p><strong>{{ !empty($order->customer) ? $order->customer->customer_name : 'Walk-in-Customer' }}</strong>
                    </p>
                    <p><strong>Order Type:</strong>
                        @if (isset($order->partner) && !empty($order->partner))
                            {{ $order->partner->partner_name }}
                        @else
                            {{ isset($data['order_type'][$order->order_type]) ? $data['order_type'][$order->order_type] : '' }}
                        @endif
                    </p>
                    <p><strong>Restaurant Date:</strong>
                        @if (!empty($order->order_date))
                            {{ Carbon\Carbon::parse($order->order_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                        @else
                            -
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <h4><strong>#{{ $order->order_serial }}</strong></h4>
                    <p><strong>Amount:</strong> {{ number_format($order->order_amount, 3) }} </p>
                    @if ($order->kitchen_time)
                        <p class="timer" data-time="{{ $order->kitchen_time }}">Time:
                            {{ $order->kitchen_time }}</p>
                    @endif
                </div>
            </div>
            <div class="d-flex justify-content-between">
                @if ($order->kitchen_status == 'ready')
                    <div class="text-right">
                        <button class="btn btn-danger btn-sm btn-style orderCompleted btn-block"
                            data-id="{{ $order->id }}">
                            <i class="tio-shopping-cart"></i>
                            Handed Over
                        </button>
                    </div>
                @elseif ($order->kitchen_status == 'cooking')
                    <div class="text-right">
                        <button class="btn btn-primary btn-sm btn-style orderReady btn-block"
                            data-id="{{ $order->id }}">
                            <i class="tio-checkmark-circle"></i>
                            Order Ready
                        </button>
                    </div>
                @else
                    <div class="text-right">
                        <button class="btn btn-success btn-sm btn-style startCooking"
                            data-id="{{ $order->id }}">Start Cooking</button>
                    </div>
                @endif
                <div>
                    <a href="/restaurant-panel/order/details/{{ $order->id }}"
                        class="btn btn-primary btn-sm btn-style">
                        <i class="tio-info"></i>
                    </a>
                    <button type="button" class="btn btn-info btn-sm btn-style direct-print-btn ml-1 d-none"
                        data-order-id="{{ $order->id }}">
                        <i class="tio-print"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-footer p-2">
            @foreach ($order->details as $detail)
                @php
                    $accordionId = 'order_item_' . $detail->id;
                @endphp

                @php
                    $accordionId = 'order_item_' . $detail->id;
                @endphp

                <div class="accordion mb-2" id="accordion_{{ $accordionId }}">
                    <div class="card">
                        <div class="card-header p-0" id="heading_{{ $accordionId }}">
                            <button
                                class="btn btn-link w-100 text-left text-decoration-none font-weight-normal p-2 accordion-toggle collapsed"
                                type="button"
                                data-toggle="collapse"
                                data-target="#collapse_{{ $accordionId }}"
                                aria-expanded="false"
                                aria-controls="collapse_{{ $accordionId }}"
                            >
                                <div class="d-flex justify-content-between align-items-center w-100">
                                    @php
                                        $foodNameArabic = App\Models\Food::where('id', $detail->food_id)->first()->getTranslationValue('name', 'ar');
                                    @endphp
                                    <div>
                                        <strong>{{ $detail->quantity }}</strong>
                                        x
                                        <strong>{{ $detail->food['name'] }}</strong>
                                        <br/>
                                        {{$foodNameArabic}}
                                    </div>
                                    
                                    <span class="accordion-arrow">
                                        &#9662;
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div
                            id="collapse_{{ $accordionId }}"
                            class="collapse"
                            aria-labelledby="heading_{{ $accordionId }}"
                            data-parent="#accordion_{{ $accordionId }}"
                        >
                            <div class="p-2 item-card-body">
                                @if (!empty($detail->variation) && count(json_decode($detail->variation, true)) > 0)
                                    @php
                                        $foodDetails = json_decode($detail->food_details, true);
                                        $variations = json_decode($detail->variation, true);
                                    @endphp

                                    @foreach ($variations as $variation)
                                        @if (isset($variation['name']) && isset($variation['values']))
                                            @foreach ($variation['values'] as $value)
                                                @php
                                                    $optionName = '';

                                                    if (!empty($variation['printing_option']) && $variation['printing_option'] == 'option_name') {
                                                        $optionName = DB::table('variation_options')
                                                            ->where('id', $value['option_id'] ?? null)
                                                            ->value('option_name') ?? '';
                                                    } else {
                                                        $option = \App\Models\OptionsList::find($value['options_list_id'] ?? null);

                                                        if ($option) {
                                                            $optionName = $option->name ?? '';
                                                        }
                                                    }
                                                @endphp

                                                @if (!empty($optionName))
                                                    <p class="mb-1">- {{ $optionName }}</p>
                                                @endif
                                            @endforeach
                                        @endif

                                        @if (isset($variation['addons']) && is_array($variation['addons']) && count($variation['addons']) > 0)
                                            <div class="variation-addons-inline mb-1">
                                                @foreach ($variation['addons'] as $addon)
                                                    @php
                                                        $addOnArabicName = App\Models\AddOn::where('id', $addon['id'])->first()->getTranslationValue('name', 'ar') ?? '';
                                                    @endphp
                                                    <span class="d-block text-capitalize">
                                                        <small class="text-muted">
                                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $addon['quantity'] }} x {{ Str::limit($addon['name'], 30, '...') }} {{ $addOnArabicName }}
                                                        </small>
                                                    </span>

                                                    @php($total_variation_addon_price += $addon['price'] * $addon['quantity'])
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <hr/>
            @if ($order->note)
                <div>
                    <strong>Note: </strong> {{ $order->note }}
                </div>
            @endif
            <div>
                <strong>Total Items: </strong> {{ count($order->details) }}
            </div>
        </div>
    </div>
</div>
