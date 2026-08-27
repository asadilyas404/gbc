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

        .highlight-new-order .card {
            animation: cardPulseGlow 12s ease-in-out;
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

    @include('vendor-views.partials._order-sound-manager')
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
                            if(type == 'ready' || type == 'completed' || type == 'handover'){
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
        const localWsHost = window.location.hostname;
        const pusherKey = '{{ env('PUSHER_APP_KEY', 'app-key') }}';
        const pusherPort = parseInt('{{ env('PUSHER_PORT', 6001) }}') || 6001;

        console.log('[DEBUG] Initializing Pusher client with key:', pusherKey, 'host:', localWsHost, 'port:', pusherPort);
        console.log('[DEBUG] Expected WebSocket URL: ws://' + localWsHost + ':' + pusherPort + '/app/' + pusherKey);

        try {
            var pusher = new Pusher(pusherKey, {
                cluster: 'mt1',
                wsHost: localWsHost,
                wsPort: pusherPort,
                forceTLS: false,
                disableStats: true,
                enabledTransports: ['ws']
            });

            console.log('[DEBUG] Pusher object created. Current state:', pusher.connection.state);

            // Bind state_change immediately to catch ALL transitions
            pusher.connection.bind('state_change', function(s) {
                console.log('[DEBUG] Pusher STATE:', s.previous, '=>', s.current);
            });

            pusher.connection.bind('error', function(err) {
                console.error('[DEBUG] Pusher connection ERROR:', err);
            });

            pusher.connection.bind('connected', function() {
                console.log('[DEBUG] Pusher CONNECTED! Socket ID:', pusher.connection.socket_id);
            });

        } catch (e) {
            console.error('[DEBUG] Pusher CONSTRUCTOR threw an error:', e);
        }

        let fallbackActive = false;
        let isSyncing = false;
        let fallbackTimeout = null;

        function clearFallbackTimer() {
            if (fallbackTimeout) {
                clearTimeout(fallbackTimeout);
                fallbackTimeout = null;
            }
        }

        function scheduleNextFallbackSync() {
            clearFallbackTimer();
            if (fallbackActive) {
                fallbackTimeout = setTimeout(function () {
                    syncKitchenOrders();
                }, 40000);
            }
        }

        function startFallbackMode() {
            if (fallbackActive) return;
            fallbackActive = true;
            console.warn("Fallback mode activated. Starting 40s sequential kitchen sync...");
            syncKitchenOrders(); // Execute immediately
        }

        function stopFallbackMode() {
            if (!fallbackActive) return;
            fallbackActive = false;
            clearFallbackTimer();
            console.log("Pusher reconnected. Fallback mode stopped.");
        }

        function applyCardHighlight($element) {
            $element.addClass('highlight-new-order');
            setTimeout(function () {
                $element.removeClass('highlight-new-order');
            }, 12000);
        }

        function fetchAndUpsertKitchenCard(orderId) {
            return $.ajax({
                url: '/restaurant-panel/order/kitchen-card/' + orderId,
                type: 'GET',
                timeout: 15000,
                success: function (response) {
                    const orderSelector = '#order_' + orderId;
                    const $existingCard = $(orderSelector);

                    if (response.matches_status === false) {
                        if ($existingCard.length) {
                            $existingCard.fadeOut(300, function () { $(this).remove(); });
                        }
                        return;
                    }

                    if ($existingCard.length) {
                        // Card already exists in DOM: update HTML, move to top, apply highlight, and play sound
                        const $newCard = $(response.html);
                        $existingCard.replaceWith($newCard);
                        $('#orders .row').prepend($newCard);
                        applyCardHighlight($newCard);
                        playNotificationSound();
                    } else {
                        // Card was genuinely missing: prepend card to top, apply highlight, and play sound
                        const $newCard = $(response.html);
                        $('#orders .row').prepend($newCard);
                        applyCardHighlight($newCard);
                        playNotificationSound();
                    }
                    updateTimers();
                },
                error: function (xhr) {
                    console.log('Could not load order HTML:', xhr.responseText);
                    if (xhr.status === 404) {
                        const orderSelector = '#order_' + orderId;
                        if ($(orderSelector).length) {
                            $(orderSelector).fadeOut(300, function () { $(this).remove(); });
                        }
                    }
                }
            });
        }

        function syncKitchenOrders() {
            if (isSyncing) return;
            isSyncing = true;

            $.ajax({
                url: '/restaurant-panel/kitchen/sync',
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

                    // 1. Remove DOM cards that no longer exist on server (completed/canceled/delivered)
                    $('[data-order-id]').each(function () {
                        const domOrderId = $(this).attr('data-order-id');
                        if (!serverOrderMap[domOrderId]) {
                            $('#order_' + domOrderId).fadeOut(300, function () {
                                $(this).remove();
                            });
                        }
                    });

                    // 2. Reconcile server orders against DOM cards using pre-rendered card HTML
                    let hasOrderChangedOrNew = false;

                    serverOrders.forEach(function (order) {
                        const orderSelector = '#order_' + order.id;
                        const $existingCard = $(orderSelector);

                        if (!$existingCard.length) {
                            // Genuinely missing order: prepend rendered html card from response payload
                            const $newCard = $(order.html);
                            $('#orders .row').prepend($newCard);
                            applyCardHighlight($newCard);
                            hasOrderChangedOrNew = true;
                        } else {
                            // Check if status changed
                            const currentKitchenStatus = $existingCard.attr('data-kitchen-status');
                            const currentOrderStatus = $existingCard.attr('data-order-status');

                            if (order.kitchen_status !== currentKitchenStatus || order.order_status !== currentOrderStatus) {
                                const $newCard = $(order.html);
                                $existingCard.replaceWith($newCard);
                                $('#orders .row').prepend($newCard);
                                applyCardHighlight($newCard);
                                hasOrderChangedOrNew = true;
                            }
                        }
                    });

                    if (hasOrderChangedOrNew) {
                        playNotificationSound();
                    }

                    updateTimers();
                },
                error: function (xhr, status, error) {
                    console.warn("Kitchen sync request failed or network unavailable (" + status + "):", error);
                },
                complete: function () {
                    isSyncing = false;
                    if (fallbackActive) {
                        scheduleNextFallbackSync();
                    }
                }
            });
        }

        // Real-time Pusher Event Listener
        var channel = pusher.subscribe('my-channel');
        channel.bind('my-event', function (data) {
            console.log('[DEBUG] Pusher my-event received:', data);
            if (data.branch_id && window.currentBranchId && parseInt(data.branch_id) !== parseInt(window.currentBranchId)) {
                console.log('[DEBUG] Event branch_id mismatch (' + data.branch_id + ' != ' + window.currentBranchId + '). Skipping.');
                return;
            }

            if (data.order_id) {
                fetchAndUpsertKitchenCard(data.order_id);
            } else {
                syncKitchenOrders();
            }
        });

        // Pusher Connection State Listener
        pusher.connection.bind('state_change', function (states) {
            console.log('[DEBUG] Pusher connection state changed:', states.previous, '=>', states.current);
            if (states.current === 'connected') {
                stopFallbackMode();
                syncKitchenOrders(); // Perform ONE immediate sync on reconnection
            } else if (states.current === 'disconnected' || states.current === 'unavailable' || states.current === 'failed') {
                startFallbackMode();
            }
        });

        // Network offline / online events
        window.addEventListener('offline', function () {
            console.warn('Network offline event detected.');
            startFallbackMode();
        });

        window.addEventListener('online', function () {
            console.log('Network online event detected.');
            if (pusher.connection.state === 'connected') {
                stopFallbackMode();
                syncKitchenOrders();
            }
        });

        // Initial check if Pusher failed to connect on page load
        setTimeout(function () {
            if (pusher.connection.state !== 'connected' || !navigator.onLine) {
                startFallbackMode();
            }
        }, 3000);

       
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
                        `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
                }

                update(); // Initial update
                setInterval(update, 1000); // Update every second
            });
        }

        updateTimers();

        setInterval(function() {
            window.location.reload();
        }, 900000);

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
