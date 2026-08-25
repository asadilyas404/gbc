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

        @keyframes cardPulseGlow {
            0% {
                box-shadow: 0 0 20px 5px rgba(255, 193, 7, 0.9);
                border: 2px solid #ffc107;
                transform: scale(1.02);
            }
            50% {
                box-shadow: 0 0 15px 3px rgba(255, 193, 7, 0.6);
                border: 2px solid #ffc107;
                transform: scale(1.01);
            }
            100% {
                box-shadow: none;
                border: 1px solid rgba(0, 0, 0, 0.125);
                transform: scale(1);
            }
        }

        .highlight-new-order .card, .order-card.highlight-new-order {
            animation: cardPulseGlow 12s ease-in-out;
        }

        @keyframes rowPulseGlow {
            0% {
                background-color: #fff3cd !important;
                box-shadow: inset 0 0 12px rgba(255, 193, 7, 0.8);
            }
            50% {
                background-color: #ffe69c !important;
                box-shadow: inset 0 0 8px rgba(255, 193, 7, 0.5);
            }
            100% {
                background-color: transparent !important;
                box-shadow: none;
            }
        }

        .highlight-new-row {
            animation: rowPulseGlow 12s ease-in-out;
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
                            <a href="{{ route('vendor.order.sync.orders') }}" class="btn max-sm-12 btn--primary w-100">
                                Sync Orders
                            </a>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div style="display: flex;gap:5px;align-items: center;">
                            <p class="my-1"><strong>Last Sync Order At:</strong> {{ $lastSync ? date('d M Y, h:i A', strtotime($lastSync)) : '-' }}</p>
                            <span> / </span>
                            <p class="my-1"><strong>Pending Orders Sync:</strong> {{ $pendingSync ?? 0 }}</p>
                            <span> / </span>
                            <p class="my-1 @if(strtotime($lastSyncRunAt) < strtotime("-5 minutes")) text-danger @endif"><strong>Last Sync Run:</strong>
                                {{ $lastSyncRunAt ? date('d M Y, h:i A', strtotime($lastSyncRunAt)) : '-' }}
                            </p>
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
                                <th class="w-100px text-center">{{translate('messages.order_partner')}}</th>
                                <th class="w-100px text-center">{{ translate('messages.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">

                            @foreach ($orders as $key => $order)
                                @include('vendor-views.order.partials._row', ['order' => $order, 'key' => $key + $orders->firstItem()])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="row g-0" id="orders-container">
                    @foreach ($orders as $order)
                        @include('vendor-views.order.partials._card', ['order' => $order])
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
                    <button type="button" class="btn btn--primary" id="quickViewProceedBtn"
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
        const currentStatusFilter = '{{ $st }}';

        $(document).on('ready', function() {
            Pusher.logToConsole = true;
            const localWsHost = window.location.hostname;
            const pusherKey = '{{ env('PUSHER_APP_KEY', 'app-key') }}';
            const pusherPort = parseInt('{{ env('PUSHER_PORT', 6001) }}') || 6001;

            var pusher = new Pusher(pusherKey, {
                cluster: 'mt1',
                wsHost: localWsHost,
                wsPort: pusherPort,
                forceTLS: false,
                disableStats: true,
                enabledTransports: ['ws']
            });

            const notificationSound = new Audio('/sounds/notification.wav');
            notificationSound.preload = 'auto';

            let orderFallbackActive = false;
            let isSyncingOrderList = false;
            let orderFallbackTimeout = null;

            function clearOrderFallbackTimer() {
                if (orderFallbackTimeout) {
                    clearTimeout(orderFallbackTimeout);
                    orderFallbackTimeout = null;
                }
            }

            function scheduleNextOrderFallbackSync() {
                clearOrderFallbackTimer();
                if (orderFallbackActive) {
                    orderFallbackTimeout = setTimeout(function () {
                        syncOrderList();
                    }, 40000);
                }
            }

            function startOrderFallbackPolling() {
                if (orderFallbackActive) return;
                orderFallbackActive = true;
                console.warn("Order list fallback mode activated. Starting 40s sequential sync...");
                syncOrderList();
            }

            function stopOrderFallbackPolling() {
                if (!orderFallbackActive) return;
                orderFallbackActive = false;
                clearOrderFallbackTimer();
                console.log("Pusher reconnected. Stopped order list fallback polling.");
            }

            function applyHighlight($element) {
                if ($element.is('tr')) {
                    $element.addClass('highlight-new-row');
                    setTimeout(function () { $element.removeClass('highlight-new-row'); }, 12000);
                } else {
                    $element.addClass('highlight-new-order');
                    setTimeout(function () { $element.removeClass('highlight-new-order'); }, 12000);
                }
            }

            function upsertOrderCard(orderId) {
                return $.ajax({
                    url: '/restaurant-panel/order/order-card/' + orderId + '?status=' + encodeURIComponent(currentStatusFilter),
                    type: 'GET',
                    timeout: 15000,
                    success: function (response) {
                        if (response.matches_status === false) {
                            $(`#order-row-${orderId}, #order-card-${orderId}`).fadeOut(300, function () { $(this).remove(); });
                            return;
                        }

                        const $existingRow = $(`#order-row-${orderId}`);
                        const $existingCard = $(`#order-card-${orderId}`);

                        if ($existingRow.length) {
                            const $newRow = $(response.html);
                            $existingRow.replaceWith($newRow);
                            if ($('#set-rows').length) $('#set-rows').prepend($newRow);
                            applyHighlight($newRow);
                        } else if ($existingCard.length) {
                            const $newCard = $(response.html);
                            $existingCard.replaceWith($newCard);
                            if ($('#orders-container').length) $('#orders-container').prepend($newCard);
                            applyHighlight($newCard);
                        } else if ($('#set-rows').length) {
                            const $newRow = $(response.html);
                            $('#set-rows').prepend($newRow);
                            applyHighlight($newRow);
                            notificationSound.currentTime = 0;
                            notificationSound.play().catch(function (error) {
                                console.log('Sound blocked:', error);
                            });
                        } else if ($('#orders-container').length) {
                            const $newCard = $(response.html);
                            $('#orders-container').prepend($newCard);
                            applyHighlight($newCard);
                            notificationSound.currentTime = 0;
                            notificationSound.play().catch(function (error) {
                                console.log('Sound blocked:', error);
                            });
                        }
                    },
                    error: function (xhr) {
                        console.log('Could not load order HTML:', xhr.responseText);
                        if (xhr.status === 404) {
                            $(`#order-row-${orderId}, #order-card-${orderId}`).fadeOut(300, function () { $(this).remove(); });
                        }
                    }
                });
            }

            function syncOrderList() {
                if (isSyncingOrderList) return;
                isSyncingOrderList = true;

                $.ajax({
                    url: '/restaurant-panel/order/sync?status=' + encodeURIComponent(currentStatusFilter),
                    type: 'GET',
                    dataType: 'json',
                    timeout: 15000,
                    success: function (response) {
                        if (!response || !response.success || !Array.isArray(response.orders)) {
                            return;
                        }

                        const serverOrders = response.orders;
                        const serverOrderMap = {};
                        serverOrders.forEach(function (order) {
                            serverOrderMap[order.id] = order;
                        });

                        // 1. Remove DOM items that no longer exist or match status filter
                        $('#set-rows > tr[data-order-id], #orders-container > div[data-order-id]').each(function () {
                            const domOrderId = $(this).attr('data-order-id');
                            if (domOrderId && !serverOrderMap[domOrderId]) {
                                $(`#order-row-${domOrderId}, #order-card-${domOrderId}`).fadeOut(300, function () {
                                    $(this).remove();
                                });
                            }
                        });

                        // 2. Reconcile server orders against DOM using pre-rendered HTML
                        let hasGenuinelyNewOrder = false;

                        serverOrders.forEach(function (order) {
                            const $existingItem = $(`#order-row-${order.id}, #order-card-${order.id}`);

                            if (!$existingItem.length) {
                                // Genuinely missing order: upsert order card / row
                                upsertOrderCard(order.id);
                                hasGenuinelyNewOrder = true;
                            } else {
                                // Check if status changed
                                const currentOrderStatus = $existingItem.attr('data-order-status');
                                const currentPaymentStatus = $existingItem.attr('data-payment-status');

                                if (order.order_status !== currentOrderStatus || order.payment_status !== currentPaymentStatus) {
                                    upsertOrderCard(order.id);
                                }
                            }
                        });

                        if (hasGenuinelyNewOrder) {
                            notificationSound.currentTime = 0;
                            notificationSound.play().catch(function (error) {
                                console.log('Sound blocked:', error);
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        console.warn("Order list sync request failed or timed out:", status, error);
                    },
                    complete: function () {
                        isSyncingOrderList = false;
                        if (orderFallbackActive) {
                            scheduleNextOrderFallbackSync();
                        }
                    }
                });
            }

            // Real-time Pusher Event Listener
            var channel = pusher.subscribe('my-channel');
            channel.bind('my-event', function(data) {
                console.log('[DEBUG] Order list my-event received:', data);
                if (data.branch_id && window.currentBranchId && parseInt(data.branch_id) !== parseInt(window.currentBranchId)) {
                    console.log('[DEBUG] Order list branch_id mismatch (' + data.branch_id + ' != ' + window.currentBranchId + '). Skipping.');
                    return;
                }
                if (data.order_id) {
                    upsertOrderCard(data.order_id);
                } else {
                    syncOrderList();
                }
            });

            // Pusher Connection State Listener
            pusher.connection.bind('state_change', function (states) {
                console.log('Order List Pusher connection state:', states.current);
                if (states.current === 'connected') {
                    stopOrderFallbackPolling();
                    syncOrderList(); // Immediate recovery sync on reconnection
                } else if (states.current === 'disconnected' || states.current === 'unavailable' || states.current === 'failed') {
                    startOrderFallbackPolling();
                }
            });

            // Network offline / online events
            window.addEventListener('offline', function () {
                console.warn('Order list network offline event detected.');
                startOrderFallbackPolling();
            });

            window.addEventListener('online', function () {
                console.log('Order list network online event detected.');
                if (pusher.connection.state === 'connected') {
                    stopOrderFallbackPolling();
                    syncOrderList();
                }
            });

            // Safety check if Pusher fails to connect on initial page load
            setTimeout(function () {
                if (pusher.connection.state !== 'connected' || !navigator.onLine) {
                    startOrderFallbackPolling();
                }
            }, 3000);


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

            const phoneInput = document.getElementById('phone');
            $(document).on('submit', 'form#order_place', function (e) {
                const form = this;
                const $form = $(form);

                const phoneInput =
                    form.querySelector('#phone, input[name="phone"]');

                const formHasPhoneInput = phoneInput !== null;
                const isDraft = $('#order_draft').val() === 'draft';

                /*
                * Get the button that submitted the form.
                */
                let submitButton =
                    e.originalEvent && e.originalEvent.submitter
                        ? e.originalEvent.submitter
                        : $form.find('button[type="submit"].clicked')[0] || null;

                /*
                * The phone field cannot be required because the user
                * is allowed to skip the phone warning.
                */
                if (formHasPhoneInput) {
                    phoneInput.removeAttribute('required');
                }

                /*
                * Payment validation only for final orders.
                */
                if (!isDraft) {
                    $("input[name='select_payment_type']")
                        .prop('required', true);
                } else {
                    /*
                    * Remove required when saving a draft.
                    * This is important if a previous submission was final.
                    */
                    $("input[name='select_payment_type']")
                        .prop('required', false);
                }

                /*
                * Run native browser validation after phone checking.
                */
                if (!form.checkValidity()) {
                    e.preventDefault();

                    form.reportValidity();
                    return false;
                }

                /*
                * Allow only the immediate resubmission caused by
                * clicking Skip.
                *
                * Remove the flag immediately so that every later
                * form submission checks the phone again.
                */
                const skipPhoneWarningOnce =
                    $form.data('skip-phone-warning-once') === true;

                if (skipPhoneWarningOnce) {
                    $form.removeData('skip-phone-warning-once');
                }

                /*
                * Show SweetAlert for both draft and final orders.
                */
                if (
                    formHasPhoneInput &&
                    phoneInput.value.trim() === '' &&
                    !skipPhoneWarningOnce
                ) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    Swal.fire({
                        title: 'Phone number is empty',
                        text: 'The customer phone number has not been entered. Do you want to continue without a phone number?',
                        icon: 'warning',
                        showCancelButton: true,

                        confirmButtonText: 'Skip',
                        cancelButtonText: 'Go back',

                        // Reverse the usual colors
                        confirmButtonColor: '#3085d6', // Gray
                        cancelButtonColor: '#6c757d',  // Blue

                        reverseButtons: true,
                        focusCancel: true,
                        allowOutsideClick: false
                    }).then(function (result) {
                        /*
                        * result.value supports older SweetAlert2 versions.
                        * result.isConfirmed supports newer versions.
                        */
                        const confirmed =
                            result.isConfirmed === true ||
                            result.value === true;

                        if (confirmed) {
                            /*
                            * Skip the warning only on the next immediate
                            * form submission.
                            */
                            $form.data(
                                'skip-phone-warning-once',
                                true
                            );

                            phoneInput.setCustomValidity('');

                            /*
                            * Resubmit using the same button so the correct
                            * action and loader are preserved.
                            */
                            if (typeof form.requestSubmit === 'function') {
                                if (submitButton) {
                                    form.requestSubmit(submitButton);
                                } else {
                                    form.requestSubmit();
                                }
                            } else {
                                $form.trigger('submit');
                            }
                        } else {
                            phoneInput.focus();
                        }
                    });

                    return false;
                }

                /*
                * Validate the phone format only when a value exists.
                */
                if (
                    formHasPhoneInput &&
                    phoneInput.value.trim() !== '' &&
                    !validatePhone()
                ) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    phoneInput.focus();
                    phoneInput.reportValidity();

                    return false;
                }


                /*
                * Prevent duplicate submissions.
                */
                if ($form.data('submitting') === true) {
                    e.preventDefault();
                    return false;
                }

                $form.data('submitting', true);

                const $buttons =
                    $form.find('button[type="submit"]');

                let $activeBtn = submitButton
                    ? $(submitButton)
                    : $form.find('button[type="submit"].clicked');

                /*
                * When Enter is pressed, use the first submit button.
                */
                if (!$activeBtn.length) {
                    $activeBtn = $buttons.first();
                }

                /*
                * Save the original HTML before adding the loader.
                */
                if (
                    $activeBtn.length &&
                    !$activeBtn.data('original-html')
                ) {
                    $activeBtn.data(
                        'original-html',
                        $activeBtn.html()
                    );
                }

                /*
                * Disable all submit buttons only after all validation
                * has successfully passed.
                */
                $buttons.prop('disabled', true);

                /*
                * Show loader on the button that submitted the form.
                */
                if ($activeBtn.length) {
                    $activeBtn.html(
                        '<span class="spinner-border spinner-border-sm mr-1" ' +
                        'role="status" aria-hidden="true"></span> Wait'
                    );
                }

                /*
                * Normal form submission continues here.
                */
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

            $('#cash_paid, #card_paid').prop('readOnly', true);
            $(document).on('change', 'input[name="select_payment_type"]', function() {
                var value = $(this).val();
                if(value == 'cash_payment'){
                    $('#cash_paid').prop('readOnly', false).val('').trigger('input');
                    $('#card_paid').prop('readOnly', true).val('').trigger('input');
                }
                if(value == 'card_payment'){
                    $('#card_paid').prop('readOnly', false).val('').trigger('input');
                    $('#cash_paid').prop('readOnly', true).val('').trigger('input');
                }
                if(value == 'both_payment'){
                    $('#cash_paid, #card_paid').prop('readOnly', false).val('').trigger('input');
                }
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
                        if (data.delivery_type) {
                            $('input[name="delivery_type"][value="' + data.delivery_type + '"]').prop('checked', true);
                        }
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
                            if($('input[name="select_payment_type"]:checked').length > 0){
                                $('input[name="select_payment_type"]').prop('checked', false);
                                $('#cash_paid, #card_paid').prop('readOnly', true).val('').trigger('input');
                            }
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

            phoneInput.addEventListener('keyup', validatePhone);
            phoneInput.addEventListener('blur', validatePhone);

            function validatePhone() {
                const phoneInput = document.getElementById('phone');

                let value = phoneInput.value.replace(/[^\d+]/g, '');

                // Allow + only as the first character
                value = value.replace(/(?!^)\+/g, '');

                phoneInput.value = value;

                // Empty phone number is allowed
                if (value.trim() === '') {
                    phoneInput.classList.remove('is-valid', 'is-invalid');
                    phoneInput.setCustomValidity('');

                    return true;
                }

                const omanMobileRegex = /^(?:(?:\+?968)?[79]\d{7}|00[1-9]\d{7,14})$/;

                const validFeedback = phoneInput
                .closest('.form-group')
                ?.querySelector('.valid-feedback');

                if (omanMobileRegex.test(value)) {
                    phoneInput.classList.add('is-valid');
                    phoneInput.classList.remove('is-invalid');
                    phoneInput.setCustomValidity('');
                    if (validFeedback) {
                        validFeedback.textContent = value.startsWith('00')
                            ? 'Valid international phone number.'
                            : 'Valid Oman mobile number.';
                    }

                    return true;
                }

                phoneInput.classList.add('is-invalid');
                phoneInput.classList.remove('is-valid');
                phoneInput.setCustomValidity(
                    value.startsWith('00')
                        ? 'Please enter a valid international phone number.'
                        : 'Please enter a valid Oman mobile number.'
                );

                return false;
            }

            Swal.fire({
                title: 'Welcome to the Pending Orders Page',
                text: 'Here you will find all pending orders that are waiting to be reviewed and processed.',
                type: 'info',
                confirmButtonText: 'View Pending Orders'
            }).then(function (result) {
                if (result.value) {
                    const sound = document.getElementById('new-order-sound');

                    if (!sound) {
                        console.error('Notification sound element not found.');
                        return;
                    }

                    sound.play()
                        .then(function () {
                            sound.pause();
                            sound.currentTime = 0;

                            window.kitchenAudioEnabled = true;
                        })
                        .catch(function (error) {
                            console.error('Unable to activate notification sound:', error);
                        });
                }
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

