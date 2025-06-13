@extends('layouts.vendor.app')

@section('title', 'New Order List')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
                <div class="row">
                    <div id="pending">

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
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->id }}</p>
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
                                                    target="_blank" class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
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

                    <!-- Cooking Tab -->
                    <div id="cooking">
                        {{-- <div class="row"> --}}
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
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->id }}</p>
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
                                                    target="_blank" class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
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
                        {{-- </div> --}}
                    </div>

                    <!-- Ready Tab -->
                    <div id="ready">
                        {{-- <div class="row"> --}}
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
                                            </div>
                                            <div class="text-right">
                                                <p><strong>Order No:</strong> {{ $order->id }}</p>
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
                                                    target="_blank" class="btn btn-primary btn-sm btn-style">Order
                                                    Detail</a>
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
                        {{-- </div> --}}
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

                            $('#pending').html(`${pendingCard}`);
                        }
                        if (cookingList) {
                            let cookingCard = ``;
                            Object.entries(cookingList).forEach(([id, item]) => {
                                let customer = (item.customer && item.customer?.name) ? item.customer
                                    ?.name : "Walk-in-Customer"
                                cookingCard += funViewCard(customer, item, 'Ready', 'orderReady');
                            });

                            $('#cooking').html(`${cookingCard}`);
                        }
                        if (readyList) {
                            let readyCard = ``;
                            Object.entries(readyList).forEach(([id, item]) => {
                                let customer = (item.customer && item.customer?.name) ? item.customer
                                    ?.name : "Walk-in-Customer"
                                readyCard += funViewCard(customer, item, 'Completed', 'orderCompleted');
                            });

                            $('#ready').html(`${readyCard}`);
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
            return `<div class="col-md-4 mb-2">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p><strong>${customer}</strong></p>
                                        <p><strong>Order Type:</strong> ${orderType?.[item.order_type] ? orderType?.[item.order_type] : ""} </p>
                                    </div>
                                    <div class="text-right">
                                        <p><strong>Order No:</strong> ${item.id}</p>
                                        <p><strong>Amount:</strong> ${item.order_amount} </p>
                                        ${timeHtml}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="/restaurant-panel/order/details/${item.id}" target="_blank" class="btn btn-primary btn-sm btn-style">Order Detail</a>
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
    </script>
@endpush
