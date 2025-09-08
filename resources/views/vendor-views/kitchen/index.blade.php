@extends('layouts.vendor.app')

@section('title', 'New Order List')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- QZ Tray Script -->
    <script src="{{ dynamicAsset('public/assets/restaurant_panel/qz-tray.js') }}"></script>

    <style>
        #pending .card-body {
            background: #e2e3e5;
        }

        .btn-style {
            padding: 5px 12px;
        }

        #cooking .card-body {
            background: #f8d7da;
        }

        #ready .card-body {
            background: #c3e6cb;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-add-circle-outlined"></i> New Order List
            </h1>
        </div>

        <div class="container mt-4">
            <!-- Tabs Navigation -->
            {{-- <ul class="nav nav-tabs" id="orderTabs">
                <li class="nav-item">
                    <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending">Pending</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cooking-tab" data-toggle="tab" href="#cooking">Cooking</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="ready-tab" data-toggle="tab" href="#ready">Ready</a>
                </li>
            </ul> --}}

            <!-- Tabs Content -->
            <div class="mt-3">
                <!-- Pending Tab -->
                <div id="pending">
                    <div class="row">
                        @foreach ($data['pending'] as $order)
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p><strong>{{ !empty($order->cutomer) ? $order->cutomer->name : 'Walk-in-Customer' }}</strong>
                                                </p>
                                                <p><strong>Order Type:</strong>
                                                    {{ isset($data['order_type'][$order->order_type]) ? $data['order_type'][$order->order_type] : '' }}
                                                </p>
                                                <p><strong>Restaurant Date:</strong>
                                                    @if(!empty($order->order_date))
                                                        {{ Carbon\Carbon::parse($order->order_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                                    @else
                                                        -
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->order_serial }}</p>
                                                <p><strong>Amount:</strong> {{ $order->order_amount }} </p>
                                                @if ($order->kitchen_time)
                                                    <p class="timer" data-time="{{ $order->kitchen_time }}">Time:
                                                        {{ $order->kitchen_time }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <a href="/restaurant-panel/order/details/{{ $order->id }}"
                                                    class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
                                                <button type="button" class="btn btn-info btn-sm btn-style direct-print-btn ml-1"
                                                    data-order-id="{{ $order->id }}">
                                                    <i class="tio-print"></i> Direct Print
                                                </button>
                                            </div>
                                            <div class="text-right">
                                                <button class="btn btn-primary btn-sm btn-style startCooking"
                                                    data-id="{{ $order->id }}">Start Cooking</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Cooking Tab -->
                <div id="cooking">
                    <div class="row">
                        @foreach ($data['cooking'] as $order)
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p><strong>{{ !empty($order->cutomer) ? $order->cutomer->name : 'Walk-in-Customer' }}</strong>
                                                </p>
                                                <p><strong>Order Type:</strong>
                                                    {{ isset($data['order_type'][$order->order_type]) ? $data['order_type'][$order->order_type] : '' }}
                                                </p>
                                                <p><strong>Restaurant Date:</strong>
                                                    @if(!empty($order->order_date))
                                                        {{ Carbon\Carbon::parse($order->order_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                                    @else
                                                        -
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->order_serial }}</p>
                                                <p><strong>Amount:</strong> {{ $order->order_amount }} </p>
                                                @if ($order->kitchen_time)
                                                    <p class="timer" data-time="{{ $order->kitchen_time }}">Time:
                                                        {{ $order->kitchen_time }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <a href="/restaurant-panel/order/details/{{ $order->id }}"
                                                    class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
                                                <button type="button" class="btn btn-info btn-sm btn-style direct-print-btn ml-1"
                                                    data-order-id="{{ $order->id }}">
                                                    <i class="tio-print"></i> Direct Print
                                                </button>
                                            </div>
                                            <div class="text-right">
                                                <button class="btn btn-primary btn-sm btn-style orderReady"
                                                    data-id="{{ $order->id }}">Ready</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Ready Tab -->
                <div id="ready">
                    <div class="row">
                        @foreach ($data['ready'] as $order)
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p><strong>{{ !empty($order->cutomer) ? $order->cutomer->name : 'Walk-in-Customer' }}</strong>
                                                </p>
                                                <p><strong>Order Type:</strong>
                                                    {{ isset($data['order_type'][$order->order_type]) ? $data['order_type'][$order->order_type] : '' }}
                                                </p>
                                                <p><strong>Restaurant Date:</strong>
                                                    @if(!empty($order->order_date))
                                                        {{ Carbon\Carbon::parse($order->order_date)->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                                    @else
                                                        -
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->order_serial }}</p>
                                                <p><strong>Amount:</strong> {{ $order->order_amount }} </p>
                                                @if ($order->kitchen_time)
                                                    <p class="timer" data-time="{{ $order->kitchen_time }}">Time:
                                                        {{ $order->kitchen_time }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <a href="/restaurant-panel/order/details/{{ $order->id }}"
                                                    class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
                                                <button type="button" class="btn btn-info btn-sm btn-style direct-print-btn ml-1"
                                                    data-order-id="{{ $order->id }}">
                                                    <i class="tio-print"></i> Direct Print
                                                </button>
                                            </div>
                                            <div class="text-right">
                                                <button class="btn btn-primary btn-sm btn-style orderCompleted"
                                                    data-id="{{ $order->id }}">Completed</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000"
        style="position: absolute; top: 1rem; right: 1rem;z-index:9999">
        <div class="toast-body" style="background: #c3e6cb">
            New order are getting from counter.
        </div>
    </div>

    <!-- Print Content Divs -->
    <div id="bill-print-content" class="d-none">
        <!-- Will be populated dynamically -->
    </div>

    <div id="kitchen-print-content" class="d-none">
        <!-- Will be populated dynamically -->
    </div>

@endsection

@push('script')
@endpush

@push('script_2')
    <script>
        var orderType = @json($data['order_type']);
        let currentRequest = null;

        function updateAllOrders(type = null, id = null) {
            if (currentRequest !== null) {
                currentRequest.abort();
            }
            let url = "/restaurant-panel/kitchen/get-all-orders";
            if (type && id) {
                url = url + "?type=" + type + "&id=" + id;
            }
            currentRequest = $.ajax({
                url: url,
                type: "GET",
                dataType: "json",
                success: function(res) {
                    if (res.success) {
                        let data = res.data;
                        let pendingList = data.pending;
                        let cookingList = data.cooking;
                        let readyList = data.ready;
                        if (pendingList) {
                            let pendingCard = ``;
                            Object.entries(pendingList).forEach(([id, item]) => {
                                let customer = (item.customer && item.customer?.name) ? item.customer
                                    ?.name : "Walk-in-Customer"
                                pendingCard += funViewCard(customer, item, 'Start Cooking',
                                    'startCooking');
                            });

                            $('#pending').html(`<div class="row">${pendingCard}<div>`);
                        }
                        if (cookingList) {
                            let cookingCard = ``;
                            Object.entries(cookingList).forEach(([id, item]) => {
                                let customer = (item.customer && item.customer?.name) ? item.customer
                                    ?.name : "Walk-in-Customer"
                                cookingCard += funViewCard(customer, item, 'Ready', 'orderReady');
                            });

                            $('#cooking').html(`<div class="row">${cookingCard}<div>`);
                        }
                        if (readyList) {
                            let readyCard = ``;
                            Object.entries(readyList).forEach(([id, item]) => {
                                let customer = (item.customer && item.customer?.name) ? item.customer
                                    ?.name : "Walk-in-Customer"
                                readyCard += funViewCard(customer, item, 'Completed', 'orderCompleted');
                            });

                            $('#ready').html(`<div class="row">${readyCard}<div>`);
                        }

                        updateTimers();

                        $('.toast').toast('show');
                    } else {
                        alert(res.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error:", error);
                }
            });
        }

        setInterval(function() {
            updateAllOrders();
        }, 10000)

        function funViewCard(customer, item, buttonName, btnAction) {
            let timeHtml = "";
            if (item?.kitchen_time) {
                let timer = item?.kitchen_time;
                if (timer) {
                    timeHtml = `<p class="timer" data-time="${timer}">Time: ${timer}</p>`;
                }
            }

            // Format order date for display
            let orderDateHtml = "";
            if (item?.order_date) {
                const orderDate = new Date(item.order_date);
                const formattedDate = orderDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                orderDateHtml = `<p><strong>Restaurant Date:</strong> ${formattedDate}</p>`;
            } else {
                orderDateHtml = `<p><strong>Restaurant Date:</strong> -</p>`;
            }

            return `<div class="col-md-4 mb-2">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p><strong>${customer}</strong></p>
                                        <p><strong>Order Type:</strong> ${orderType?.[item.order_type] ? orderType?.[item.order_type] : ""} </p>
                                        ${orderDateHtml}
                                    </div>
                                    <div class="text-right">
                                        <p><strong>Order No:</strong> ${item.order_serial}</p>
                                        <p><strong>Amount:</strong> ${item.order_amount} </p>
                                        ${timeHtml}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="/restaurant-panel/order/details/${item.id}" class="btn btn-primary btn-sm btn-style">Order Detail</a>
                                        <button type="button" class="btn btn-info btn-sm btn-style direct-print-btn ml-1 mt-1" data-order-id="${item.id}">
                                            <i class="tio-print"></i> Direct Print
                                        </button>
                                    </div>
                                    <div class="text-right">
                                        <button class="btn btn-primary btn-sm btn-style ${btnAction}" data-id="${item.id}">${buttonName}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
        }

        $(document).on('click', '.startCooking', function() {
            const thix = $(this);
            const data_id = thix.attr('data-id');
            if (data_id) {
                updateAllOrders('cooking', data_id);
            }
        });

        $(document).on('click', '.orderReady', function() {
            const thix = $(this);
            const data_id = thix.attr('data-id');
            if (data_id) {
                updateAllOrders('ready', data_id);
            }
        });

        $(document).on('click', '.orderCompleted', function() {
            const thix = $(this);
            const data_id = thix.attr('data-id');
            if (data_id) {
                updateAllOrders('completed', data_id);
            }
        });

        @php
            $timezone = \App\Models\BusinessSetting::where('key', 'timezone')->first();
            $setTimezone = !empty($timezone->value) ? $timezone->value : 'Asia/Muscat';
        @endphp

        function updateTimers() {
            const timerElements = document.querySelectorAll(".timer");

            timerElements.forEach(timerElement => {
                const startTime = timerElement.getAttribute("data-time"); // Get initial time
                const [startHours, startMinutes, startSeconds] = startTime.split(":").map(Number);

                function getKarachiTime() {
                    return new Date(new Date().toLocaleString("en-US", {
                        timeZone: "{{ $setTimezone }}"
                    }));
                }

                const startDate = getKarachiTime();
                startDate.setHours(startHours, startMinutes, startSeconds, 0);

                function update() {
                    const now = getKarachiTime();
                    const diff = Math.floor((now - startDate) / 1000); // Difference in seconds

                    const hours = Math.floor(diff / 3600);
                    const minutes = Math.floor((diff % 3600) / 60);
                    const seconds = diff % 60;

                    timerElement.textContent =
                        `Time: ${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
                }

                update(); // Initial update
                setInterval(update, 1000); // Update every second
            });
        }

        updateTimers();

        setInterval(function() {
            window.location.reload();
        }, 600000);

        // $(document).on('click', '.direct-print-btn', function() {
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
    </script>
@endpush
