<tr class="status-{{ $order['order_status'] }} class-all @if ($order['order_status'] === 'cooking') order-status-cooking @endif" id="order-row-{{ $order['id'] }}" data-order-id="{{ $order['id'] }}" data-order-status="{{ $order['order_status'] }}" data-payment-status="{{ $order['payment_status'] }}">
    <td class="">
        {{ $key ?? 1 }}
    </td>
    <td class="table-column-pl-0">
        <a href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"
            class="text-hover">{{ $order['order_serial'] }}</a>
    </td>
    <td>
        <span class="d-block">
            {{ \Carbon\Carbon::parse($order['created_at'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
        </span>
        <span class="d-block text-uppercase">
            {{ \Carbon\Carbon::parse($order['created_at'])->locale(app()->getLocale())->translatedFormat(config('timeformat')) }}
        </span>
    </td>
    <td>
        <span class="d-block">
            @if(!empty($order['order_date']))
                {{ \Carbon\Carbon::parse($order['order_date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
            @else
                -
            @endif
        </span>
    </td>
    <td>
        @if ($order->is_guest)
            @php
                $customer_details = json_decode($order['delivery_address'], true);
            @endphp
            <strong>{{ $customer_details['contact_person_name'] ?? '-' }}</strong>
            <div>{{ $customer_details['contact_person_number'] ?? '-' }}</div>
        @elseif($order->customer)
            <a class="text-body text-capitalize"
                href="{{ route('vendor.order.details', ['id' => $order['id']]) }}">
                <span class="d-block font-semibold">
                    {{ $order->customer['customer_name'] }}
                </span>
                <span class="d-block">
                    {{ $order->customer['customer_mobile_no'] }}
                </span>
            </a>
        @else
            @if (
                $order->pos_details &&
                    ($order->pos_details->customer_name || $order->pos_details->car_number || $order->pos_details->phone))
                @if ($order->pos_details->customer_name)
                    <div>Name: {{ $order->pos_details->customer_name }}</div>
                @endif
                @if ($order->pos_details->car_number)
                    <div>Car: {{ $order->pos_details->car_number }}</div>
                @endif
                @if ($order->pos_details->phone)
                    <div>Phone: {{ $order->pos_details->phone }}</div>
                @endif
            @else
                <label
                    class="badge badge-danger">{{ translate('messages.invalid_customer_data') }}</label>
            @endif
        @endif
    </td>
    <td>
        <div class="text-right mw-85px">
            <div>
                {{ \App\CentralLogics\Helpers::format_currency($order['order_amount']) }}
            </div>
            @if ($order->payment_status == 'paid')
                <strong class="text-success">
                    {{ translate('messages.paid') }}
                </strong>
            @elseif($order->payment_status == 'partially_paid')
                <strong class="text-success">
                    {{ translate('messages.partially_paid') }}
                </strong>
            @else
                <strong class="text-danger">
                    {{ translate('messages.unpaid') }}
                </strong>
            @endif
        </div>
    </td>
    <td class="text-capitalize text-center">
        @if (isset($order->subscription) && $order->subscription->status != 'canceled')
            @php
                $order->order_status = $order->subscription_log
                    ? $order->subscription_log->order_status
                    : $order->order_status;
            @endphp
        @endif
        @if ($order['order_status'] == 'canceled')
            <span class="badge badge-soft-warning mb-1">
                {{ translate('messages.canceled') }}
            </span>
        @else
            <span class="badge badge-soft-info mb-1">
                {{ translate(str_replace('_', ' ', $order['order_status'])) }}
            </span>
        @endif

        <div class="text-capitalze opacity-7">
            @if ($order['order_type'] == 'take_away')
                <span>
                    {{ translate('messages.take_away') }}
                </span>
            @elseif ($order['order_type'] == 'dine_in')
                <span>
                    {{ translate('messages.dine_in') }}
                </span>
            @else
                <span>
                    {{ translate('messages.delivery') }}
                </span>
            @endif
        </div>
    </td>
    <td class="">
        {{ $order['partner_name'] ?? '-' }}
    </td>
    <td>
        <div class="btn--container justify-content-center">
            <a class="btn action-btn btn--warning btn-outline-warning"
                href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"><i
                    class="tio-visible-outlined"></i></a>
            
            <a class="btn action-btn btn--warning btn-outline-warning"
                href="{{ route('vendor.pos.load-draft', ['order_id' => $order->id]) }}"
                title="{{ translate('Load Unpaid to POS') }}">
                <i class="tio-refresh"></i>
            </a>
        
            <a class="btn action-btn btn--primary btn-outline-primary" target="_blank"
                href="{{ route('vendor.order.generate-invoice', [$order['id']]) }}"><i
                    class="tio-print"></i></a>
            <a class="btn action-btn btn--primary btn-outline-primary" target="_blank"
                title="Order Receipt"
                href="{{ route('vendor.order.generate-order-receipt', [$order['id']]) }}"><i
                    class="tio-document"></i></a>
        </div>
    </td>
</tr>
