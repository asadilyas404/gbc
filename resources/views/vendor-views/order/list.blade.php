@extends('layouts.vendor.app')

@section('title', translate('messages.Order List'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        .order-card {
            transition: all 0.2s ease-in-out;
            border-radius: 0.5rem;
        }

        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
            border-color: #0d6efd;
        }

        .order-card .badge {
            font-size: 1rem;
            padding: 0.35em 0.6em;
        }

        .order-card .order-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .order-card small,
        .order-card .text-muted {
            font-size: 0.95rem;
        }

        .order-card .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.95rem;
        }
    </style>
@endpush

@section('content')
    <?php
    use Illuminate\Support\Str;
    $isDraftPage = Str::contains(request()->url(), 'draft');
    $totalAmount = $orders->sum('order_amount');
    ?>
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header pt-0 pb-2">
            <div class="d-flex flex-wrap justify-content-between">
                <h2 class="page-header-title align-items-center text-capitalize py-2 mr-2">
                    <div class="card-header-icon d-inline-flex mr-2 img">
                        @if (str_replace('_', ' ', $status) == 'All')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/order.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Pending')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/pending.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Confirmed')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/confirm.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Cooking')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/cooking.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Ready for delivery')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/ready.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Food on the way')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/ready.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Delivered')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/ready.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Refunded')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/order.png') }}"
                                alt="public">
                        @elseif(str_replace('_', ' ', $status) == 'Scheduled')
                            <img class="mw-24px"
                                src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/order.png') }}"
                                alt="public">
                        @endif
                    </div>
                    <span>
                        {{ str_replace('_', ' ', $status) }} {{ translate('messages.orders') }} <span
                            class="badge badge-soft-dark ml-2">{{ $orders->total() }}</span>
                    </span>
                </h2>
            </div>
            @if (app()->environment('local'))
                <div class="my-2">
                    <div class="row g-2 align-items-center justify-content-end">

                        <div class="col-auto">
                            <a href="{{ route('vendor.order.sync.orders') }}" class="btn max-sm-12 btn--primary w-100">Sync
                                Orders</a>
                        </div>

                    </div>
                </div>
            @endif
        </div>
        <!-- End Page Header -->


        <!-- End Page Header -->

        <!-- Card -->
        <div class="card">
            <!-- Header -->
            <div class="card-header py-2">
                <div class="row w-100 align-items-center">
                    <!-- Left: Total Amount -->
                    <div class="col-md-6 col-sm-12 mb-2 mb-md-0">
                        <h3 class="mb-0 text-dark">
                            {{ translate('Total Order Amount') }}:
                            <span class="text-primary">
                                {{ \App\CentralLogics\Helpers::format_currency($totalAmount) }}
                            </span>
                        </h3>
                    </div>

                    <!-- Right: Search Bar -->
                    <div class="col-md-6 col-sm-12">
                        <div class="search--button-wrapper justify-content-end max-sm-flex-100">
                            <form>
                                <!-- Search -->
                                <div class="input-group input--group">
                                    <input id="datatableSearch_" type="search" name="search" class="form-control"
                                        value="{{ request()->search ?? null }}"
                                        placeholder="{{ translate('Ex : Search by Order Id') }}"
                                        aria-label="{{ translate('messages.search') }}">
                                    <button type="submit" class="btn btn--secondary">
                                        <i class="tio-search"></i>
                                    </button>
                                </div>
                                <!-- End Search -->
                            </form>

                            <div class="d-sm-flex justify-content-sm-end align-items-sm-center m-0">

                                <!-- Unfold -->
                                <div class="hs-unfold mr-2">
                                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle" href="javascript:;"
                                        data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                                        <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                                    </a>

                                    <div id="usersExportDropdown"
                                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                        <span class="dropdown-header">{{ translate('messages.options') }}</span>
                                        <a id="export-copy" class="dropdown-item" href="javascript:;">
                                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{ dynamicAsset('public/assets/admin') }}/svg/illustrations/copy.svg"
                                                alt="Image Description">
                                            {{ translate('messages.copy') }}
                                        </a>
                                        <a id="export-print" class="dropdown-item" href="javascript:;">
                                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{ dynamicAsset('public/assets/admin') }}/svg/illustrations/print.svg"
                                                alt="Image Description">
                                            {{ translate('messages.print') }}
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                                        <a id="export-excel" class="dropdown-item"
                                            href="{{ route('vendor.order.export', ['status' => $st, 'type' => 'excel', request()->getQueryString()]) }}">
                                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{ dynamicAsset('public/assets/admin') }}/svg/components/excel.svg"
                                                alt="Image Description">
                                            {{ translate('messages.excel') }}
                                        </a>
                                        <a id="export-csv" class="dropdown-item"
                                            href="{{ route('vendor.order.export', ['status' => $st, 'type' => 'csv', request()->getQueryString()]) }}">
                                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{ dynamicAsset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                                alt="Image Description">
                                            {{ translate('messages.csv') }}
                                        </a>
                                        <a id="export-pdf" class="dropdown-item" href="javascript:;">
                                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{ dynamicAsset('public/assets/admin') }}/svg/components/pdf.svg"
                                                alt="Image Description">
                                            {{ translate('messages.pdf') }}
                                        </a>
                                    </div>
                                </div>
                                <!-- End Unfold -->

                                <!-- Unfold -->
                                <div class="hs-unfold">
                                    <a class="js-hs-unfold-invoker btn btn-sm btn-white" href="javascript:;"
                                        data-hs-unfold-options='{
                                    "target": "#showHideDropdown",
                                    "type": "css-animation"
                                }'>
                                        <i class="tio-table mr-1"></i> {{ translate('messages.column') }} <span
                                            class="badge badge-soft-dark rounded-circle ml-1"></span>
                                    </a>

                                    <div id="showHideDropdown"
                                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right dropdown-card">
                                        <div class="card card-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="mr-2">
                                                        {{ translate('messages.Order_ID') }}

                                                    </span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm"
                                                        for="toggleColumn_order">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_order" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="mr-2">{{ translate('messages.date') }}</span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm" for="toggleColumn_date">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_date" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="mr-2">{{ translate('messages.customer') }}</span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm"
                                                        for="toggleColumn_customer">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_customer" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>


                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="mr-2">{{ translate('messages.total') }}</span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm"
                                                        for="toggleColumn_total">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_total" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="mr-2">{{ translate('messages.order_status') }}</span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm"
                                                        for="toggleColumn_order_status">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_order_status" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="mr-2">{{ translate('messages.actions') }}</span>

                                                    <!-- Checkbox Switch -->
                                                    <label class="toggle-switch toggle-switch-sm"
                                                        for="toggleColumn_actions">
                                                        <input type="checkbox" class="toggle-switch-input"
                                                            id="toggleColumn_actions" checked>
                                                        <span class="toggle-switch-label">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                    <!-- End Checkbox Switch -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- <div class="hs-unfold mr-2">
                            <form method="POST" action="{{ route('vendor.order.sync.orders') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-white">Sync Orders</button>
                            </form>
                        </div> --}}
                                <!-- End Unfold -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Header -->

            <!-- Table -->
            @if (!$isDraftPage)
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="w-60px">
                                    {{ translate('messages.sl') }}
                                </th>
                                <th class="w-90px table-column-pl-0">{{ translate('messages.Order_ID') }}</th>
                                <th class="w-140px">{{ translate('messages.order_date') }}</th>
                                <th class="w-140px">{{ translate('messages.customer_information') }}</th>
                                <th class="w-100px">{{ translate('messages.total_amount') }}</th>
                                <th class="w-100px text-center">{{ translate('messages.order_status') }}</th>
                                <th class="w-100px text-center">{{ translate('messages.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                            @foreach ($orders as $key => $order)
                                <tr class="status-{{ $order['order_status'] }} class-all">
                                    <td class="">
                                        {{ $key + $orders->firstItem() }}
                                    </td>
                                    <td class="table-column-pl-0">
                                        <a href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"
                                            class="text-hover">{{ $order['order_serial'] }}</a>
                                    </td>
                                    <td>
                                        <span class="d-block">
                                            {{ Carbon\Carbon::parse($order['created_at'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                        </span>
                                        <span class="d-block text-uppercase">
                                            {{ Carbon\Carbon::parse($order['created_at'])->locale(app()->getLocale())->translatedFormat(config('timeformat')) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($order->is_guest)
                                            <?php
                                            $customer_details = json_decode($order['delivery_address'], true);
                                            ?>
                                            <strong>{{ $customer_details['contact_person_name'] }}</strong>
                                            <div>{{ $customer_details['contact_person_number'] }}</div>
                                        @elseif($order->customer)
                                            <a class="text-body text-capitalize"
                                                href="{{ route('vendor.order.details', ['id' => $order['id']]) }}">
                                                <span class="d-block font-semibold">
                                                    {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}
                                                </span>
                                                <span class="d-block">
                                                    {{ $order->customer['phone'] }}
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
                                            {{-- @elseif($order['order_status'] == 'confirmed')
                                        <span class="badge badge-soft-info mb-1">
                                            {{ translate('messages.confirmed') }}
                                        </span>
                                    @elseif($order['order_status'] == 'processing')
                                        <span class="badge badge-soft-warning mb-1">
                                            {{ translate('messages.processing') }}
                                        </span>
                                    @elseif($order['order_status'] == 'picked_up')
                                        <span class="badge badge-soft-warning mb-1">
                                            {{ translate('messages.out_for_delivery') }}
                                        </span>
                                    @elseif($order['order_status'] == 'delivered')
                                        <span class="badge badge-soft-success mb-1">
                                            {{ translate('messages.delivered') }}
                                        </span> --}}
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
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn btn--warning btn-outline-warning"
                                                href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"><i
                                                    class="tio-visible-outlined"></i></a>
                                            @if ($order['payment_status'] == 'unpaid')
                                                <a class="btn action-btn btn--warning btn-outline-warning"
                                                    href="{{ route('vendor.pos.load-draft', ['order_id' => $order->id]) }}"
                                                    title="{{ translate('Load Unpaid to POS') }}">
                                                    <i class="tio-refresh"></i>
                                                </a>
                                            @endif
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
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="row">
                    @foreach ($orders as $order)
                        <div class="col-md-6 col-xl-4 p-2">
                            <div class="card border order-card h-100 shadow-sm">
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

                                    <!-- Date -->
                                    <div class="text-muted mb-1">
                                        <strong>{{ translate('messages.order_date') }}:</strong><br>
                                        {{ \Carbon\Carbon::parse($order['created_at'])->format('d M Y - h:i A') }}
                                    </div>

                                    <!-- Customer Info -->
                                    <div class="text-muted mb-1">
                                        <strong>{{ translate('messages.customer_information') }}:</strong><br>
                                        @if ($order->is_guest)
                                            @php $cust = json_decode($order['delivery_address'], true); @endphp
                                            {{ $cust['contact_person_name'] ?? '-' }}<br>
                                            {{ $cust['contact_person_number'] ?? '-' }}
                                        @elseif($order->customer)
                                            {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}<br>
                                            {{ $order->customer['phone'] }}
                                        @elseif($order->pos_details)
                                            {{ $order->pos_details->customer_name ?? '-' }}<br>
                                            Phone: {{ $order->pos_details->phone ?? '-' }} &nbsp;
                                            Car No. {{ $order->pos_details->car_number ?? '-' }}
                                        @endif
                                    </div>

                                    <!-- Amount -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="text-muted">
                                            <strong>{{ translate('messages.total_amount') }}:</strong>
                                            {{ \App\CentralLogics\Helpers::format_currency($order['order_amount']) }}
                                        </div>

                                        <div>
                                            @if ($order->payment_status === 'paid')
                                                <span
                                                    class="badge bg-success text-white small">{{ translate('messages.paid') }}</span>
                                            @elseif($order->payment_status === 'partially_paid')
                                                <span
                                                    class="badge bg-warning text-white small">{{ translate('messages.partially_paid') }}</span>
                                            @else
                                                <span
                                                    class="badge bg-danger text-white small">{{ translate('messages.unpaid') }}</span>
                                            @endif
                                        </div>
                                    </div>


                                    <!-- Action Buttons -->
                                    <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                                        <a href="{{ route('vendor.order.details', ['id' => $order['id']]) }}"
                                            class="btn btn-sm btn-outline-primary" title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>

                                        <a href="javascript:void(0);" class="btn btn-sm btn-outline-info quick-view-btn"
                                            data-order-id="{{ $order['id'] }}"
                                            data-order-number="{{ $order['order_serial'] }}"
                                            title="{{ translate('Quick View') }}">
                                            <i class="tio-info-outined"></i>
                                        </a>

                                        @if ($order['payment_status'] === 'unpaid')
                                            <a href="{{ route('vendor.pos.load-draft', ['order_id' => $order->id]) }}"
                                                class="btn btn-sm btn-outline-warning"
                                                title="{{ translate('Load to POS') }}">
                                                <i class="tio-refresh"></i>
                                            </a>
                                        @endif

                                        <a href="{{ route('vendor.order.generate-invoice', [$order['id']]) }}"
                                            class="btn btn-sm btn-outline-success" target="_blank"
                                            title="{{ translate('Invoice') }}">
                                            <i class="tio-print"></i>
                                        </a>

                                        <a href="{{ route('vendor.order.generate-order-receipt', [$order['id']]) }}"
                                            class="btn btn-sm btn-outline-dark" target="_blank"
                                            title="{{ translate('Receipt') }}">
                                            <i class="tio-document"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>


            @endif
            @if (count($orders) === 0)
                <div class="empty--data">
                    <img src="{{ dynamicAsset('/public/assets/admin/img/empty.png') }}" alt="public">
                    <h5>
                        {{ translate('no_data_found') }}
                    </h5>
                </div>
            @endif
            <!-- End Table -->

            <!-- Footer -->
            <div class="card-footer">
                <!-- Pagination -->
                <div class="row justify-content-center justify-content-sm-between align-items-sm-center">
                    <div class="col-sm-auto">
                        <div class="d-flex justify-content-center justify-content-sm-end">
                            <!-- Pagination -->
                            {!! $orders->links() !!}
                        </div>
                    </div>
                </div>
                <!-- End Pagination -->
            </div>
            <!-- End Footer -->
        </div>
        <!-- End Card -->
    </div>

    <div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light py-3">
                    <h4 class="modal-title">{{ translate('Order Items') }} for <span id="modal-order-number"
                            style="font-size: 1.5rem">--</span></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-hover table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Item') }}</th>
                                <th class="text-center">{{ translate('Qty') }}</th>
                                <th>{{ translate('Price') }}</th>
                            </tr>
                        </thead>
                        <tbody id="quick-view-items-body">
                            {{-- Dynamically filled --}}
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn--primary" id="quickViewProceedBtn" data-order-id=""
                        data-dismiss="modal" data-toggle="modal" data-target="#orderFinalModal">
                        {{ translate('Proceed') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('vendor-views.pos.orderFinalModal')


@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function() {
            // INITIALIZATION OF NAV SCROLLER
            // =======================================================
            $('.js-nav-scroller').each(function() {
                new HsNavScroller($(this)).init()
            });

            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function() {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });


            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'copy',
                        className: 'd-none'
                    },
                    {
                        extend: 'pdf',
                        className: 'd-none'
                    },
                    {
                        extend: 'print',
                        className: 'd-none'
                    },
                ],
                select: {
                    style: 'multi',
                    selector: 'td:first-child input[type="checkbox"]',
                    classMap: {
                        checkAll: '#datatableCheckAll',
                        counter: '#datatableCounter',
                        counterInfo: '#datatableCounterInfo'
                    }
                },
                language: {
                    zeroRecords: '<div class="text-center p-4">' +
                        '<img class="mb-3 w-7rem" src="{{ dynamicAsset('public/assets/admin') }}/svg/illustrations/sorry.svg" alt="Image Description">' +
                        '<p class="mb-0">{{ translate('No_data_to_show') }}</p>' +
                        '</div>'
                }
            });

            $('#export-copy').click(function() {
                datatable.button('.buttons-copy').trigger()
            });

            $('#export-excel').click(function() {
                datatable.button('.buttons-excel').trigger()
            });

            $('#export-csv').click(function() {
                datatable.button('.buttons-csv').trigger()
            });

            $('#export-pdf').click(function() {
                datatable.button('.buttons-pdf').trigger()
            });

            $('#export-print').click(function() {
                datatable.button('.buttons-print').trigger()
            });

            $('#toggleColumn_order').change(function(e) {
                datatable.columns(1).visible(e.target.checked)
            })

            $('#toggleColumn_date').change(function(e) {
                datatable.columns(2).visible(e.target.checked)
            })

            $('#toggleColumn_customer').change(function(e) {
                datatable.columns(3).visible(e.target.checked)
            })

            $('#toggleColumn_order_status').change(function(e) {
                datatable.columns(5).visible(e.target.checked)
            })


            $('#toggleColumn_total').change(function(e) {
                datatable.columns(4).visible(e.target.checked)
            })

            $('#toggleColumn_actions').change(function(e) {
                datatable.columns(6).visible(e.target.checked)
            })


            // INITIALIZATION OF TAGIFY
            // =======================================================
            $('.js-tagify').each(function() {
                let tagify = $.HSCore.components.HSTagify.init($(this));
            });
        });


        $(document).on('click', '.quick-view-btn', function() {
            const orderId = $(this).data('order-id');
            const orderNumber = $(this).data('order-number');
            $('#modal-order-number').text(orderNumber);

            const modalBody = $('#quick-view-items-body');
            modalBody.html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');

            const url = "{{ route('vendor.order.quickView', ['id' => '__id__']) }}".replace('__id__', orderId);

            console.log('Fetching:', url); // Debug
            $('#quickViewModal').modal('show');

            $('#quickViewProceedBtn').data('order-id', orderId);

            $.get(url, function(response) {
                modalBody.html(response);
            }).fail(function() {
                modalBody.html(
                    '<tr><td colspan="3" class="text-danger text-center">Failed to load data.</td></tr>'
                );
            });
        });

        $('#orderFinalModal').on('show.bs.modal', function() {
            const orderId = $('#quickViewProceedBtn').data('order-id');
            if (!orderId) return;

            // Make AJAX call to get order payment data
            $.get("{{ url('/vendor/order/payment-data') }}/" + orderId, function(data) {
                // Fill modal fields (example only; adjust field names as needed)
                $('#invoice_amount span').text(data.total_amount_formatted);
                $('#customer_name').val(data.customer_name);
                $('#car_number').val(data.car_number);
                $('#phone').val(data.phone);
                $('#cash_paid').val(data.cash_paid);
                $('#card_paid').val(data.card_paid);
                $('#delivery_type').val(data.delivery_type);
                $('#bank_account').val(data.bank_account).prop('disabled', !data.bank_account);
            });
        });

        $('#orderFinalModal').on('hidden.bs.modal', function() {
            $('#customer_name, #car_number, #phone, #cash_paid, #card_paid').val('');
            $('#bank_account').val('').prop('disabled', true);
            $('#invoice_amount span').text('0.0');
        });
    </script>
@endpush
