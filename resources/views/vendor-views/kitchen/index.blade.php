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

        #cooking .item-card-body{
            background: #f9e2e4;
        }
        #cooking .card-body, #cooking .card-header  {
            background: #f8d7da;
        }

        #ready .card-body {
            background: #c3e6cb;
        }
        .accordion-toggle {
            color: #212529;
        }

        .accordion-toggle:hover {
            color: #212529;
            text-decoration: none;
        }

        .accordion-arrow {
            font-size: 18px;
            transition: transform 0.2s ease;
        }

        .accordion-toggle:not(.collapsed) .accordion-arrow {
            transform: rotate(180deg);
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
                <div id="orders">
                    <div class="row">
                        @foreach ($data['orders'] as $order)
                            @include('vendor-views.kitchen.partials._card', $order)
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
                        let orders = data.orders;
                        let pendingList = data.pending;
                        let cookingList = data.cooking;
                        let readyList = data.ready;
                        if (orders) {
                            if(type == 'ready' || type == 'completed'){
                                $("#order_" + id).fadeOut(300, function () {
                                    $(this).remove();
                                });
                            }else{
                                window.location.reload();
                            }
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


        // setInterval(function() {
        //     updateAllOrders();
        // }, 10000)

         Pusher.logToConsole = true;
            var pusher = new Pusher('3072d0c5201dc9141481', {
            cluster: 'ap2',
            // forceTLS: true,
            //   enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling']
            enabledTransports: ['ws', 'wss']
            });

            const notificationSound = new Audio('/sounds/notification.wav');
            notificationSound.preload = 'auto';
            var channel = pusher.subscribe('my-channel');
            channel.bind('my-event', function (data) {
            $.ajax({
                url: '/restaurant-panel/order/kitchen-card/' + data.order_id,
                type: 'GET',
                success: function (response) {
                    const orderSelector = '#order_' + data.order_id;

                    if ($(orderSelector).length) {
                        // Order already exists, update its HTML
                        $(orderSelector).replaceWith(response.html);
                    } else {
                        // New order, add it to the top
                        $('#orders .row').prepend(response.html);

                        notificationSound.currentTime = 0;
                        notificationSound.play().catch(function (error) {
                            console.log('Sound blocked:', error);
                        });
                    }

                    updateTimers();
                },
                error: function (xhr) {
                    console.log('Could not load order HTML:', xhr.responseText);
                }
            });
        });

       
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
            thix.prop('disabled', true);
            thix.text('Processing...');
            if (data_id) {
                updateAllOrders('cooking', data_id);
            }
        });

        $(document).on('click', '.orderReady', function () {
            const thix = $(this);
            const dataId = thix.attr('data-id');

            Swal.fire({
                title: 'Are you sure?',
                text: 'You want to mark this order as ready!',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, mark as ready!'
            }).then((result) => {
                if (result.value) {
                    thix.prop('disabled', true);
                    thix.text('Processing...');
                    if (dataId) {
                        updateAllOrders('ready', dataId);
                    }
                }
            });
        });

        $(document).on('click', '.orderCompleted', function() {
            const thix = $(this);
            const data_id = thix.attr('data-id');
            thix.prop('disabled', true);
            thix.text('Processing...');
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

        // Save scroll position before the page unloads
        window.addEventListener("beforeunload", function () {
            localStorage.setItem("scrollPosition", window.scrollY);
        });

        // Restore scroll position on page load
        window.addEventListener("load", function () {
            const scrollY = localStorage.getItem("scrollPosition");
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
            }
        });

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
