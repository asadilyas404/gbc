<div class="col-md-6 col-xl-4 p-2" id="order-card-{{ $order['id'] }}">
    @php
        $authId = auth('vendor')->id() ?? auth('vendor_employee')->id();
    @endphp
    <div
        class="card border order-card h-100 shadow-sm @if ($authId && $authId == $order->order_taken_by) bg-card-mine-order border-success @endif">
        <div class="card-body p-3 pb-2">
            <!-- Header: Order # and Status -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="order-title text-dark">
                    Order #{{ $order['order_serial'] }}
                </div>
                <span
                    class="badge bg-{{ $order['order_status'] === 'canceled' ? 'danger' : 'primary' }} text-white text-capitalize">
                    {{ translate(str_replace('_', ' ', $order['order_status'])) }}
                </span>
            </div>

            <!-- Partner name -->
            @if (!empty($order->partner_name))
                <div class="text-muted mb-1">
                    <strong>Order Partner: </strong>{{ $order->partner_name }}
                </div>
            @endif

            <!-- Date -->
            <div class="text-muted mb-1">
                <strong>{{ translate('messages.order_time') }}: </strong>
                {{ \Carbon\Carbon::parse($order['created_at'])->format('d M Y - h:i A') }}
            </div>

            <div class="text-muted mb-1">
                <strong>{{ translate('messages.restaurant_date') }}: </strong> &nbsp;
                @if (!empty($order['order_date']))
                    {{ Carbon\Carbon::parse($order['order_date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
                @else
                    -
                @endif
            </div>

            <!-- Customer Info -->
            <div class="text-muted mb-1">
                <strong>{{ translate('messages.customer_information') }}:</strong><br>
                @if ($order->is_guest)
                    @php $cust = json_decode($order['delivery_address'], true); @endphp
                    {{ $cust['contact_person_name'] ?? '-' }}<br>
                    {{ $cust['contact_person_number'] ?? '-' }}
                @elseif($order->customer)
                    {{ $order->customer['customer_name'] }}<br>
                    {{ $order->customer['customer_mobile_no'] }}
                @elseif($order->pos_details)
                    {{ $order->pos_details->customer_name ?? '-' }}<br>
                    Phone: {{ $order->pos_details->phone ?? '-' }} &nbsp;
                    Car No. {{ $order->pos_details->car_number ?? '-' }}
                @endif
            </div>

            <div class="text-muted">
                <strong>{{ translate('messages.order_taken_by') }}:</strong>
                {{ $order->order_taken_by_name ?? '-' }}
            </div>
            <div class="text-muted">
                <strong>{{ translate('messages.order_type') }}:</strong>
                {{ $order->order_type ?? '-' }}
            </div>
            <!-- Amount -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted">
                    <strong>{{ translate('messages.total_amount') }}:</strong>
                    {{ \App\CentralLogics\Helpers::format_currency($order['order_amount']) }}
                </div>

                <div>
                    @if ($order->payment_status === 'paid')
                        <span class="badge bg-success text-white small">{{ translate('messages.paid') }}</span>
                    @elseif($order->payment_status === 'partially_paid')
                        <span
                            class="badge bg-warning text-white small">{{ translate('messages.partially_paid') }}</span>
                    @else
                        <span class="badge bg-danger text-white small">{{ translate('messages.unpaid') }}</span>
                    @endif
                </div>
            </div>


            <!-- Action Buttons -->
            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                <a href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"
                    class="btn btn-md btn-outline-primary" title="{{ translate('View') }}">
                    <i class="tio-visible-outlined"></i>
                </a>

                <a href="javascript:void(0);" class="btn btn-md btn-outline-info quick-view-btn"
                    data-order-id="{{ $order['id'] }}" data-order-p-id="{{ $order['partner_id'] }}"
                    data-order-number="{{ $order['order_serial'] }}" title="{{ translate('Quick View') }}">
                    <i class="tio-info-outined"></i>
                </a>

                @if ($order['payment_status'] === 'unpaid')
                    <a href="{{ route('vendor.pos.load-draft', ['order_id' => $order->id]) }}"
                        class="btn btn-md btn-outline-warning" title="{{ translate('Load to POS') }}">
                        <i class="tio-refresh"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
