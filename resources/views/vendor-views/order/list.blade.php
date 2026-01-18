@extends('layouts.vendor.app')
@php
    use App\CentralLogics\Helpers;
@endphp
@section('title', translate('messages.Order List'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- QZ Tray Script -->
    <script src="{{ dynamicAsset('public/assets/restaurant_panel/qz-tray.js') }}"></script>
 

    <style>
        .col-auto {
            padding-left: 0.25rem !important;
            padding-right: 0.25rem !important;
        }
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

        /* Minimal Statistics Cards */
        .minimal-stats {
            margin-bottom: 1rem;
        }

        .mini-card {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            padding: 0.5rem 0.75rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            min-width: 80px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .mini-card:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }

        .mini-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .mini-value {
            font-size: 1rem;
            font-weight: 700;
            color: #495057;
            line-height: 1;
        }

        /* Color variants */
        .mini-card.paid {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .mini-card.paid .mini-value {
            color: #155724;
        }

        .mini-card.unpaid {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .mini-card.unpaid .mini-value {
            color: #721c24;
        }

        .mini-card.partial {
            background: #fff3cd;
            border-color: #ffeaa7;
        }

        .mini-card.partial .mini-value {
            color: #856404;
        }

        .mini-card.amount {
            background: #cce5ff;
            border-color: #b3d9ff;
        }

        .mini-card.amount .mini-value {
            color: #004085;
        }

        .mini-card.paid-amount {
            background: #d1ecf1;
            border-color: #bee5eb;
        }

        .mini-card.paid-amount .mini-value {
            color: #0c5460;
        }

        .mini-card.unpaid-amount {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .mini-card.unpaid-amount .mini-value {
            color: #721c24;
        }

        .mini-card.pos {
            background: #e2e3e5;
            border-color: #d6d8db;
        }

        .mini-card.pos .mini-value {
            color: #383d41;
        }

        .mini-card.pos-unpaid {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .mini-card.pos-unpaid .mini-value {
            color: #721c24;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .mini-card {
                min-width: 70px;
                padding: 0.4rem 0.6rem;
            }

            .mini-label {
                font-size: 0.7rem;
            }

            .mini-value {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .mini-card {
                min-width: 60px;
                padding: 0.3rem 0.5rem;
            }

            .mini-label {
                font-size: 0.65rem;
            }

            .mini-value {
                font-size: 0.8rem;
            }
        }
        .payment-selection-box{
            border: 0.0625rem solid #e7eaf3 !important;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .bg-card-mine-order{
            background-color: #00800012 !important;
        }
    </style>
@endpush

@section('content')
    <?php
    use Illuminate\Support\Str;
    $isDraftPage = Str::contains(request()->url(), 'draft');
    ?>
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header pt-0 pb-2">
            <div class="d-flex flex-wrap justify-content-between">
                <div class="d-flex align-items-center">
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
            <div class="card-header py-1">
                <!-- Minimal Statistics Cards -->
                <div class="minimal-stats mb-1">
                    <div class="row g-2">
                        <div class="col-auto">
                            <div class="mini-card amount">
                                <span class="mini-label">{{ translate('Total_Orders') }}</span>
                                <span class="mini-value">{{ $totalOrders }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card paid">
                                <span class="mini-label">{{ translate('Paid') }}</span>
                                <span class="mini-value">{{ $paidOrders }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card unpaid">
                                <span class="mini-label">{{ translate('Unpaid') }}</span>
                                <span class="mini-value">{{ $unpaidOrders }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card canceled">
                                <span class="mini-label">{{ translate('Canceled Items') }}</span>
                                <span class="mini-value">{{ $deletedItems }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card unpaid-amount">
                                <span class="mini-label">{{ translate('Credit_Customer_Amount') }}</span>
                                <span class="mini-value">{{ \App\CentralLogics\Helpers::format_currency($creditCustomerAmount) }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card unpaid-amount">
                                <span class="mini-label">{{ translate('Credit_Partner_Amount') }}</span>
                                <span class="mini-value">{{ \App\CentralLogics\Helpers::format_currency($creditPartnerAmount) }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card unpaid-amount">
                                <span class="mini-label">{{ translate('Unpaid_Amount') }}</span>
                                <span class="mini-value">{{ \App\CentralLogics\Helpers::format_currency($unpaidAmount) }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card paid">
                                <span class="mini-label">{{ translate('Paid_Amount') }}</span>
                                <span class="mini-value">{{ \App\CentralLogics\Helpers::format_currency($paidAmount) }}</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="mini-card amount">
                                <span class="mini-label">{{ translate('Total_Amount') }}</span>
                                <span class="mini-value">{{ \App\CentralLogics\Helpers::format_currency($totalAmount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Actions Row -->
                <div class="row w-70 align-items-center">
                    <!-- Search Bar -->
                    <div class="col-12">
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
                                <th class="w-140px">{{ translate('messages.restaurant_date') }}</th>
                                <th class="w-140px">{{ translate('messages.customer_information') }}</th>
                                <th class="w-100px">{{ translate('messages.total_amount') }}</th>
                                <th class="w-100px text-center">{{ translate('messages.order_status') }}</th>
                                <th class="w-100px text-center">Order Partner</th>
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
                                        <span class="d-block">
                                            @if(!empty($order['order_date']))
                                                {{ Carbon\Carbon::parse($order['order_date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                            @else
                                                -
                                            @endif
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
                                      <td class="">
                                        {{ $order['partner_name'] }}
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

                                            {{-- <a type="button" class="btn action-btn btn--primary btn-outline-primary print-order-btn"
                                                data-order-id="{{ $order['id'] }}"
                                                title="{{ translate('Direct Print') }}">
                                                <i class="tio-print"></i>
                                            </a> --}}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="row g-0">
                    @foreach ($orders as $order)
                        <div class="col-md-6 col-xl-4 p-2">
                            @php
                                $authId = auth('vendor')->id() ?? auth('vendor_employee')->id();
                            @endphp
                            <div class="card border order-card h-100 shadow-sm @if($authId && $authId == $order->order_taken_by) bg-card-mine-order border-success @endif">
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
                                    @if(!empty($order->partner_name))
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
                                       @if(!empty($order['order_date']))
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
                                            class="btn btn-md btn-outline-primary" title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>

                                        <a href="javascript:void(0);" class="btn btn-md btn-outline-info quick-view-btn"
                                            data-order-id="{{ $order['id'] }}"
                                            data-order-p-id="{{ $order['partner_id'] }}"
                                            data-order-number="{{ $order['order_serial'] }}"
                                            title="{{ translate('Quick View') }}">
                                            <i class="tio-info-outined"></i>
                                        </a>

                                        @if ($order['payment_status'] === 'unpaid')
                                            <a href="{{ route('vendor.pos.load-draft', ['order_id' => $order->id]) }}"
                                                class="btn btn-md btn-outline-warning"
                                                title="{{ translate('Load to POS') }}">
                                                <i class="tio-refresh"></i>
                                            </a>
                                        @endif
                                        {{-- <a type="button" class="btn btn-sm btn--primary btn-outline-primary print-order-btn"
                                            data-order-id="{{ $order['id'] }}"
                                            title="{{ translate('Direct Print') }}">
                                            <i class="tio-print"></i>
                                        </a> --}}
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

    <!-- Print Content Divs -->
    <div id="bill-print-content" class="d-none">
        <!-- Will be populated dynamically -->
    </div>

    <div id="kitchen-print-content" class="d-none">
        <!-- Will be populated dynamically -->
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
            


            Pusher.logToConsole = true;
            var pusher = new Pusher('3072d0c5201dc9141481', {
            cluster: 'ap2',
            // forceTLS: true,
            //   enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling']
            enabledTransports: ['ws', 'wss']
            });

            var channel = pusher.subscribe('my-channel');
            channel.bind('my-event', function(data) {
                // if(data.message =='unpaid'){
                    window.location.reload();
                // }
                console.log(data,data.message);
               
            });

            ///////////////
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



            //Order final Model Calculations

            function formatCurrency(amount) {
                return `{{ Helpers::currency_symbol() }} ${amount.toFixed(3)}`;
            }

            function updateCalculations() {
                const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                // console.log('amount ' + invoiceAmount);
                const cashPaid = parseFloat($('#cash_paid').val()) || 0;
                const cardPaid = parseFloat($('#card_paid').val()) || 0;
                const totalPaid = cashPaid + cardPaid;
                const cashReturn = Math.max(totalPaid - invoiceAmount, 0);

                $('#cash_paid_display').text(formatCurrency(cashPaid));
                $('#cash_return').text(formatCurrency(cashReturn));
                const bankAccountSelect = $('#bank_account');

                // Validate card_paid amount
                if (cardPaid > invoiceAmount) {
                    alert('{{ translate('Card amount cannot be greater than the invoice amount.') }}');
                    $('#card_paid').val('');
                    bankAccountSelect.prop('required', false).prop('disabled', true);
                    return;
                }

                // Enable/disable bank account selection
                if (cardPaid > 0) {
                    bankAccountSelect.prop('required', true).prop('disabled', false);
                } else {
                    bankAccountSelect.prop('required', false).prop('disabled', true);
                }
            }

            function attachEventListeners() {
                $('#cash_paid, #card_paid').off('input').on('input', function() {
                    const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                    let paymentType = $('input[name="select_payment_type"]:checked').val();
                    if (paymentType === 'both_payment') {

                        let cardPaid = parseFloat($('#card_paid').val()) || 0;
                        let cashPaid = parseFloat($('#cash_paid').val()) || 0;

                        // If user typed in card field
                        if ($(this).attr('id') === 'card_paid') {
                            let remaining = invoiceAmount - cardPaid;
                            remaining = Math.max(remaining, 0);

                            $('#cash_paid').val(remaining.toFixed(3));
                            $('#cash_paid_display').text(formatCurrency(remaining));
                        }

                        // If user typed in cash field
                        if ($(this).attr('id') === 'cash_paid') {
                            let remaining = invoiceAmount - cashPaid;
                            remaining = Math.max(remaining, 0);

                            $('#card_paid').val(remaining.toFixed(3));
                            // $('#card_paid_display').text(formatCurrency(remaining));
                        }
                    }
                });
            }

            $(document).on('submit', 'form#order_place', function (e) {
                const $form = $(this);
                // Per-form lock (instead of global lock)
                
                if ($('#order_draft').val() !== 'draft') {
                    // Check if any payment type is selected
                    console.log($('input[name="select_payment_type"]:checked'));
                    if ($('input[name="select_payment_type"]:checked').length === 0) {
                        Swal.fire({
                            title: 'Select Payment Method',
                            type: 'warning'
                        });
                        return false;
                    }
                }
                
                if ($form.data('submitting')) {
                    e.preventDefault();
                    return false;
                }
                $form.data('submitting', true);

                const $buttons = $form.find('button[type="submit"]');
                let $activeBtn = $form.find('button.clicked');

                // If user pressed Enter and no button was clicked, pick first submit button
                if (!$activeBtn.length) {
                    $activeBtn = $buttons.first();
                }

                $buttons.prop('disabled', true);

                // Spinner only on active button
                if ($activeBtn.length) {
                    if (!$activeBtn.data('original-html')) {
                    $activeBtn.data('original-html', $activeBtn.html());
                    }
                    $activeBtn.html(
                    '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Wait'
                    );
                }

                // Fallback reset if submit is blocked or page doesn't unload (AJAX, validation, etc.)
                const reset = () => {
                    $form.data('submitting', false);
                    $buttons.prop('disabled', false).removeClass('clicked');

                    $buttons.each(function () {
                    const $btn = $(this);
                    const original = $btn.data('original-html');
                    if (original) $btn.html(original);
                    });
                };

                // If browser doesn't navigate within X seconds, unlock UI
                // setTimeout(reset, 15000);

                // If HTML5 validation fails, submit event may fire but navigation won't happen in some flows
                // This catches invalid forms before submit
                if (this.checkValidity && !this.checkValidity()) {
                    reset();
                    // Let browser show validation messages
                    return true;
                }
                // Allow normal submission (no preventDefault)
            });

            // Call updateCalculations when the modal is opened
            $('#orderFinalModal').on('shown.bs.modal', function() {
                updateCalculations(); // Recalculate on modal open
                attachEventListeners(); // Ensure input listeners are attached
            });

            // Trigger calculations if the modal inputs are dynamically added
            $(document).on('input', '#cash_paid, #card_paid', function() {
                updateCalculations();
            });

            $(document).on('change', 'input[name="select_payment_type"]', function() {
                var value = $(this).val();
                handlePaymentTypeChange(value);
            });

            function handlePaymentTypeChange(value) {
                if(value == 'cash_payment'){
                    const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                    $('#cash_paid').val(invoiceAmount.toFixed(3)).trigger('input');
                    $('#card_paid').val('').trigger('input');
                }

                if(value == 'card_payment'){
                    const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                    $('#cash_paid').val('').trigger('input');
                    $('#card_paid').val(invoiceAmount.toFixed(3)).trigger('input');
                }

                if(value == 'both_payment'){
                    $('#cash_paid').val(0);
                    $('#card_paid').val(0);
                }
            }


            // Numeric Keypad working

            let activeInput = null;

            $(document).on('focus', '#orderFinalModal input', function() {
                activeInput = $(this);
            });

            $(document).on('click', '.keypad-btn', function() {
                const value = $(this).data('value');
                if (activeInput) {
                    let currentVal = activeInput.val();

                    if (value === '.') {
                        if (!currentVal.includes('.')) {
                            activeInput.val(currentVal + value);
                            activeInput.trigger('input');
                        }
                    } else {
                        const newValue = currentVal + value;

                        if (isValidNumber(newValue)) {
                            activeInput.val(newValue);
                            activeInput.trigger('input');
                        } else {
                            alert('Invalid input');
                        }
                    }
                }
            });

            // Clear the input field
            $(document).on('click', '.keypad-clear', function() {
                if (activeInput) {
                    activeInput.val('');
                    activeInput.trigger('input');
                }
            });

            // Sanitize and validate input on blur
            $('#orderFinalModal').on('blur', '#cash_paid, #card_paid', function() {
                const currentVal = this.value;

                // Check if the value is a valid number
                if (!isValidNumber(currentVal)) {
                    alert('Please enter a valid number');
                    this.value = ''; // Clear the input if it's invalid
                    $(this).trigger('input');
                }

                // Remove trailing decimal point on blur
                if (currentVal.endsWith('.')) {
                    this.value = currentVal.slice(0, -1);
                    $(this).trigger('input');
                }
            });

            // Function to validate if the value is a valid number
            const isValidNumber = (value) => {
                // Check if value is numeric and not empty
                return !isNaN(value);
                //  && value.trim() !== '';
            };



            $(document).on('click', '.quick-view-btn', function() {
                const orderId = $(this).data('order-id');
                const p_Id = $(this).data('order-p-id');
                const orderNumber = $(this).data('order-number');
                console.log(orderNumber);
                $('#modal-order-number').text(orderNumber);

                const modalBody = $('#quick-view-items-body');
                modalBody.html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                let url = "{{ route('vendor.order.quickView', ['id' => '__id__']) }}".replace('__id__',
                    orderId);

                if (p_Id) {
                    url = url + '/' + p_Id; 
                }

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
                $('#loading').show();

                const orderId = $('#quickViewProceedBtn').data('order-id');
                if (!orderId) {
                    $('#loading').hide();
                    toastr.error('Order ID not found. Please try again.');
                    return;
                }

                const urlPayment = "{{ route('vendor.order.paymentData', ['id' => '__id__']) }}".replace(
                    '__id__',
                    orderId);

                $.ajax({
                    url: urlPayment,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.error) {
                            toastr.error(data.error);
                            $('#loading').hide();
                            return;
                        }

                        $('#invoice_amount span').text(data.total_amount_formatted ??
                            '{{ translate('N/A') }}');
                        $('#customer_name').val(data.customer_name ?? '');
                        $('#car_number').val(data.car_number ?? '');
                        $('#phone').val(data.phone ?? '');
                        // $('#cash_paid').val(data.cash_paid ?? '');
                        // $('#card_paid').val(data.card_paid ?? '');
                        $('#delivery_type').val(data.delivery_type ?? '');
                        $('#bank_account').val(data.bank_account ?? '');
                        $('#partner_id').val(data.partner_id ?? '');
                        $('#invoice_amount_input').val(data.total_amount_formatted ?? '');
                        
                        if (data.partner_id){
                            $('#payment_type_credit').prop('checked', true);
                            $('.payment_type').prop('disabled', true);
                            $('<input>').attr({
                            type: 'hidden',
                            name: 'select_payment_type',
                            value: 'credit_payment'
                            }).appendTo('#order_place');
                        }else{
                            $('#payment_type_credit').prop('checked', false);
                            $('#payment_type_credit').prop('disabled', true);
                            handlePaymentTypeChange('cash_payment');                        
                        }

                        updateCalculations();

                        $('#loading').hide();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error, xhr.responseText);

                        let message = 'Something went wrong. Please try again.';

                        if (xhr.status === 404) {
                            message = 'Order not found.';
                        } else if (xhr.status === 500) {
                            message = 'Server error while loading order details.';
                        }

                        toastr.error(message);
                        $('#loading').hide();
                    }
                });
            });


            $('#orderFinalModal').on('hidden.bs.modal', function() {
                $('#customer_name, #car_number, #phone, #cash_paid, #card_paid').val('');
                $('#bank_account').val('').prop('disabled', true);
                $('#invoice_amount span').text('0.0');
            });

            // Print Order Functionality
            // $(document).on('click', '.print-order-btn', function() {
            //     const orderId = $(this).data('order-id');
            //     printOrder(orderId);
            // });

            // function printOrder(orderId) {
            //     toastr.info('Preparing print...');

            //     $.ajax({
            //         url: "{{ route('vendor.order.print-order', ['id' => '__id__']) }}".replace('__id__', orderId),
            //         method: 'GET',
            //         dataType: 'json',
            //         success: function(response) {
            //             if (response.success) {
            //                 $('#bill-print-content').html(response.bill_content);
            //                 $('#kitchen-print-content').html(response.kitchen_content);

            //                 initializePrinters();
            //             } else {
            //                 toastr.error(response.message || 'Failed to prepare print content');
            //             }
            //         },
            //         error: function(xhr, status, error) {
            //             console.error('Print preparation error:', error);
            //             toastr.error('Failed to prepare print content');
            //         }
            //     });
            // }

            // function initializePrinters() {
            //     if (typeof qz === 'undefined') {
            //         toastr.error('QZ Tray is not available. Please install QZ Tray.');
            //         return;
            //     }

            //     if (!qz.websocket.isActive()) {
            //         toastr.error('QZ Tray is not connected. Please ensure QZ Tray is running and connected.');
            //         return;
            //     }

            //     const billPrinterName = '{{ config("app.bill_printer_name", "Bill Printer") }}';
            //     const kitchenPrinterName = '{{ config("app.kitchen_printer_name", "Kitchen Printer") }}';

            //     let printersFound = 0;

            //     qz.printers.find(billPrinterName).then(function(printer) {
            //         const config = qz.configs.create(printer);
            //         const printableWrapper = document.getElementById('bill-print-content');
            //         if (!printableWrapper) {
            //             toastr.error("Bill print content not found");
            //             return;
            //         }
            //         const printableDiv = printableWrapper.querySelector('#printableArea');
            //         if (!printableDiv) {
            //             toastr.error("Printable content (#printableArea) not found");
            //             return;
            //         }

            //         const clone = printableDiv.cloneNode(true);
            //         clone.querySelectorAll('.non-printable').forEach(el => el.remove());

            //         let fullHtml = document.documentElement.outerHTML;
            //         fullHtml = fullHtml.replace(
            //             /<body[^>]*>[\s\S]*<\/body>/i,
            //             `<body>${clone.innerHTML}</body>`
            //         );

            //         const data = [{
            //             type: 'html',
            //             format: 'plain',
            //             data: fullHtml
            //         }];

            //         return qz.print(config, data);
            //     }).then(() => {
            //         console.log("Bill print done");
            //         printersFound++;
            //         if (printersFound === 2) {
            //             toastr.success('Both prints completed successfully!');
            //         }
            //     }).catch(err => {
            //         console.error("Bill print failed:", err);
            //         toastr.error("Bill print failed: " + err);
            //     });

            //     qz.printers.find(kitchenPrinterName).then(function(printer) {
            //         const config = qz.configs.create(printer);
            //         const printableWrapper = document.getElementById('kitchen-print-content');
            //         if (!printableWrapper) {
            //             toastr.error("Kitchen print content not found");
            //             return;
            //         }
            //         const printableDiv = printableWrapper.querySelector('#printableArea');
            //         if (!printableDiv) {
            //             toastr.error("Printable content (#printableArea) not found");
            //             return;
            //         }

            //         const clone = printableDiv.cloneNode(true);
            //         clone.querySelectorAll('.non-printable').forEach(el => el.remove());

            //         let fullHtml = document.documentElement.outerHTML;
            //         fullHtml = fullHtml.replace(
            //             /<body[^>]*>[\s\S]*<\/body>/i,
            //             `<body>${clone.innerHTML}</body>`
            //         );

            //         const data = [{
            //             type: 'html',
            //             format: 'plain',
            //             data: fullHtml
            //         }];

            //         return qz.print(config, data);
            //     }).then(() => {
            //         console.log("Kitchen print done");
            //         printersFound++;
            //         if (printersFound === 2) {
            //             toastr.success('Both prints completed successfully!');
            //         }
            //     }).catch(err => {
            //         console.error("Kitchen print failed:", err);
            //         toastr.error("Kitchen print failed: " + err);
            //     });
            // }

            // if (typeof qz !== 'undefined') {
            //     if (!qz.websocket.isActive()) {
            //         qz.websocket.connect().then(() => {
            //             console.log('QZ Tray connected successfully');
            //         }).catch(err => {
            //             console.log("QZ Tray connection failed: " + err);
            //         });
            //     } else {
            //         console.log('QZ Tray already connected');
            //     }
            // } else {
            //     console.log('QZ Tray not available');
            // }

        });

        ///////////
        // Save scroll position before leaving or reloading
        window.addEventListener('beforeunload', function () {
            localStorage.setItem('scrollPosition', window.scrollY);
        });

        // Restore scroll position when page loads
        window.addEventListener('load', function () {
            const scrollPosition = localStorage.getItem('scrollPosition');
            if (scrollPosition) {
            window.scrollTo(0, parseInt(scrollPosition));
            // Clear it if you only want to restore once
            localStorage.removeItem('scrollPosition');
            }
        });
    </script>


@endpush

