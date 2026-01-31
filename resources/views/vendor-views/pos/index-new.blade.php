@php
    use App\CentralLogics\Helpers;
    use App\Models\BusinessSetting;
    use App\Models\Order;

    $setting = \DB::table('business_settings')->where('key', 'print_keys')->first();
    $billPrinter = $kitchenPrinter = null;

    if ($setting) {
        $printers = json_decode($setting->value, true);
        $billPrinter = $printers['bill_print'] ?? null;
        $kitchenPrinter = $printers['kitchen_print'] ?? null;
    }
@endphp
@extends('layouts.vendor.app')

@section('title', translate('messages.pos'))

@section('content')

    <style>
        .category-scroll-container {
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 0;
        }

        .category-scroll {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .category-item {
            display: inline-block;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .order_partner {
            min-width: 92px;
        }
        
        .order_partner .partner-name{
            color: #333;
        }
        .order_partner.selected .partner-name{
            color: #fff;
        }

        .order_partner:hover .partner-name{
            color: #fff;
        }
        
        .category-item.selected, .order_partner.selected, .order_partner:hover   {
            padding: 5px;
            border-radius: 10px;
            background-color: #F8923B;
            color: #fff !important;
            box-shadow: 0 4px 10px rgba(64, 169, 255, 0.5);
        }

        .category-item.selected:hover {
            color: #fff;
        }

        .partner-name{
            text-align: center;
            
        }

        .category-icon{
            text-align: center;
        }
        .category-icon img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 5px;
        }

        .category-name {
            font-size: 12px;
            text-overflow: ellipsis;
            overflow: hidden;
            word-wrap: break-word;
        }

        .category-item:not(.selected):hover {
            color: #F8923B;
        }

        .subcategory-item:not(.selected):hover {
            color: #F8923B;
        }

        .numeric-keypad-container {
            max-width: 200px;
            text-align: center;
        }

        .keypad-buttons .btn {
            width: 40px;
            height: 40px;
            margin: 5px;
            font-size: 18px;
        }

        .keypad-container h6 {
            font-weight: bold;
            margin-bottom: 10px;
        }

        @media screen and (min-width: 1026px) and (max-width: 1200px) {
            .order--pos-right {
                padding: 0px 0px 0 80px !important;
            }
        }
        .payment-selection-box{
            border: 0.0625rem solid #e7eaf3 !important;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
    </style>

    <div id="pos-div" class="content container-fluid" style="background-color: white; padding-top: 0;">
        @php($restaurant_data = Helpers::get_restaurant_data())
        <div class="d-flex flex-wrap">
            <div class="order--pos-left">
                <!-- Subcategories (Vertical Scroll Attached to Card) -->

                {{-- @if ($subcategories->isNotEmpty()) --}}
                <style>
                    /* Subcategory Scroll Styles */
                    .main-content, .footer {
                        margin-left: 80px;
                    }

                    [dir="rtl"] .main-content, [dir="rtl"] .footer {
                        margin-left: 0;
                        margin-right: 80px;
                    }

                    .subcategory-scroll-container {
                        position: fixed;
                        top: 5;
                        left: 0;
                        height: 100vh;
                        width: 80px;
                        background-color: #334257;
                        overflow-y: auto;
                        padding: 5px;
                        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
                        z-index: 1000;
                    }

                    [dir="rtl"] .subcategory-scroll-container {
                        left: auto;
                        right: 0;
                        text-align: right;
                    }

                    .subcategory-header {
                        font-size: 12px;
                        font-weight: bold;
                        text-align: center;
                        margin-bottom: 20px;
                        color: white;
                    }

                    .subcategory-list {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 10px;
                    }

                    .subcategory-item {
                        text-decoration: none;
                        display: block;
                        text-align: center;
                        color: white;
                    }

                    .subcategory-circle {
                        width: 70px;
                        height: 70px;
                        border-radius: 50%;
                        background-color: #edf3f9;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 11px;
                        font-weight: bold;
                        color: #6c757d;
                        transition: background-color 0.3s, transform 0.3s;
                    }

                    .subcategory-circle:hover {
                        background-color: #40c4ff;
                        color: black;
                        transform: scale(1.1);
                    }

                    .subcategory-item.selected {
                        padding: 5px;
                        border-radius: 10px;
                        color: white;
                        background-color: #F8923B;
                        transform: scale(1.1);
                    }

                    .subcategory-name {
                        text-align: center;
                        padding: 5px;
                        word-wrap: break-word;
                    }

                    .mobile-scroll {
                        display: none;
                    }

                    @media (max-width: 1025px) {
                        .subcategory-scroll-container {
                            width: 0px;
                            display: none;
                        }

                        .mobile-scroll {
                            display: block;
                        }

                        .main-content {
                            margin-left: 0px;
                        }

                        .main-content {
                            margin-right: 0px;
                        }

                        .subcategory-scroll-container {
                            width: 0px;
                        }

                        .subcategory-list {
                            display: flex;
                            flex-direction: row;
                            align-items: center;
                            gap: 10px;
                        }

                        .category-name {
                            color: black;
                        }
                    }
                    .pe-none{
                        pointer-events: none !important;
                    }
                </style>

                <div class="subcategory-scroll-container">
                    <h6 class="subcategory-header">
                        {{ $categories->firstWhere('id', $category)->name ?? translate('Sub_Categories') }}
                    </h6>
                    <div class="subcategory-list">
                        {{-- @foreach ($subcategories as $subCategory)
                            <a href="{{ url()->current() }}?category_id={{ $subCategory->id }}"
                                class="subcategory-item {{ request()->get('category_id') == $subCategory->id ? 'selected' : '' }}">
                                <div class="category-icon">
                                    <img src="{{ $subCategory['image_full_url'] }}" alt="{{ $subCategory->name }}">
                                </div>
                                <div class="category-name">{{ $subCategory->name }}</div>
                            </a>
                        @endforeach --}}
                        @include('vendor-views.pos._subcategory_list', ['subcategories' => $subcategories])
                    </div>
                </div>
                {{-- @endif --}}

                <div class="card main-content">
                    <div class="card-header bg-light border-0" style="padding: 5px 15px;">
                        <div class="col-sm-4">
                            <h5 class="card-title">
                                <span>
                                    {{ translate('Food Section') }}
                                </span>
                            </h5>
                        </div>
                        <div class="col-sm-8">
                            <form id="search-form" class="header-item w-100 mw-100">
                                <!-- Search -->
                                <div class="input-group input-group-merge input-group-flush w-100">
                                    <div class="input-group-prepend pl-2">
                                        <div class="input-group-text">
                                            <i class="tio-search"></i>
                                        </div>
                                    </div>
                                    {{-- <input id="datatableSearch" type="search" value="{{ $keyword ?? '' }}" name="search"
                                        class="form-control flex-grow-1 pl-5 border rounded h--45x"
                                        placeholder="{{ translate('messages.Ex : Search Food Name') }}"
                                        aria-label="{{ translate('messages.search_here') }}"> --}}
                                    <input id="search-keyword" type="search" value="{{ $keyword ?? '' }}" name="keyword"
                                        class="form-control flex-grow-1 pl-5 border rounded h--45x"
                                        placeholder="{{ translate('messages.Ex : Search Food Name') }}"
                                        aria-label="{{ translate('messages.search_here') }}">
                                </div>
                                <!-- End Search -->
                            </form>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center" id="items"
                        style="padding-top: 8px;">
                        <div class="row g-2 mb-1">
                            <div class="col-sm-12">
                                <div class="category-scroll-container">
                                    <div class="category-scroll">
                                        <a href="javascript:void(0);"
                                            class="order_partner {{ empty($orderPartner) ? 'selected' : '' }}"
                                            data-value="">
                                            <div class="category-icon">
                                                <img src="{{ asset('images/malekalpizza.png') }}"
                                                    alt="{{ config('app.name') }}">
                                            </div>
                                            <div class="partner-name">
                                                {{ config('app.name') }}
                                            </div>
                                        </a>
                                        @foreach ($orderPartners as $partner)
                                            <a href="javascript:void(0);"
                                                class="order_partner {{ $partner->partner_id == $orderPartner ? 'selected' : '' }}"
                                                data-value="{{ $partner->partner_id }}">
                                                <div class="category-icon">
                                                    @php($image = 'images/' . \Str::slug($partner->partner_name) . '.png')
                                                    <img src="{{ file_exists(public_path($image)) ? asset($image) : dynamicAsset('/public/assets/admin/img/100x100/food.png') }}" alt="{{$partner->partner_name }}">
                                                </div>
                                                <div class="partner-name">
                                                    {{ Str::limit($partner->partner_name, 20, '...') }}
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 mobile-scroll">
                                <div class="category-scroll-container">
                                    <div class="subcategory-list">
                                        @include('vendor-views.pos._subcategory_list', [
                                            'subcategories' => $subcategories,
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="product-list">
                            @include('vendor-views.pos._product_list', ['products' => $products])
                        </div>
                    </div>

                    <div class="card-footer">
                        {{--                        {!! $products->withQueryString()->links() !!} --}}
                    </div>
                </div>
            </div>
            <div class="order--pos-right">
                <div class="d-flex justify-content-between align-items-center mb-2 mt-1">
                    <div class="order-date-display">
                        <div>
                            <span class="text-muted">Order Date:</span>
                            <span class="fw-bold {{ $orderDate ? 'text-primary' : 'text-muted' }}">
                                @if ($orderDate)
                                    {{ \Carbon\Carbon::parse($orderDate)->format('F j, Y') }}
                                @else
                                    Not set
                                @endif
                            </span>
                        </div>
                    </div>
                    
                    <a class="btn btn--primary" href="{{ route('vendor.dashboard') }}"
                        title="{{ translate('messages.dashboard') }}">
                        {{ translate('messages.dashboard') }}
                    </a>
                </div>
                <div class="card">
                    <div class="card-header bg-light border-0 m-1 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <span>
                                    {{ translate('Billing Section') }}
                                </span>
                                
                            </h5>
                            <p class="text-muted mb-0">
                                {{\App\CentralLogics\Helpers::get_branch_name()}}
                            </p>
                        </div>
                        <a class="btn btn--primary btn-sm" href="{{ route('vendor.order.list', ['draft']) }}"
                            title="{{ translate('messages.Unpaid Orders') }}">
                            {{ translate('messages.Unpaid Orders') }}
                        </a>
                    </div>
                    <div class="w-100">
                        @if($editingOrder)
                            <div class="justify-content-between p-2" id="editingOrderHeading">
                                <h1 class="bg-dark px-1 rounded-sm text-white">Order # {{ $editingOrder->order_serial }}</h1>
                                @if($editingOrder->kitchen_status == 'cooking')
                                    <span class="badge bg-danger text-white small">Cooking: {{ $editingOrder->kitchen_status }}</span>
                                @elseif ($editingOrder->kitchen_status == 'completed' || $editingOrder->kitchen_status == 'ready')
                                    <span class="badge bg-success text-white small">Cooking: {{ $editingOrder->kitchen_status }}</span>
                                @else
                                    <span class="badge bg-info text-white small">Cooking: {{ $editingOrder->kitchen_status }}</span>
                                @endif
                                @if($editingOrder->payment_status === 'paid')
                                    <span
                                        class="badge bg-success text-white text-capitalize">
                                        Payment: {{ translate('paid') }}
                                    </span>
                                @endif
                            </div>
                        @endif
                        <div class="justify-content-between p-2">
                            <h1 class="bg-dark px-1 rounded-sm text-white d-none" id="newOrderHeading">New Order</h1>
                        </div>

                        <div class="d-flex flex-wrap flex-row p-2 add--customer-btn">
                            <label for='customer'></label>
                            <select id='customer' @if(!empty($orderPartner)) disabled @endif name="customer_id"
                                data-placeholder="{{ translate('messages.walk_in_customer') }}"
                                class="js-data-example-ajax form-control"></select>
                            <button class="btn btn--primary" data-toggle="modal"
                                data-target="#add-customer" @if(!empty($orderPartner)) disabled @endif>{{ translate('Add New Customer') }}</button>
                        </div>
                        @if (
                            ($restaurant_data->restaurant_model == 'commission' && $restaurant_data->self_delivery_system == 1) ||
                                ($restaurant_data->restaurant_model == 'subscription' &&
                                    isset($restaurant_data->restaurant_sub) &&
                                    $restaurant_data->restaurant_sub->self_delivery == 1))
                            <div class="pos--delivery-options">
                                <div class="d-flex justify-content-between">
                                    @if (empty($orderPartner))
                                        <h5 class="card-title">
                                            <span class="card-title-icon">
                                                <i class="tio-user"></i>
                                            </span>
                                            <span>{{ translate('Delivery_Information') }}</span>
                                        </h5>
                                    
                                        <span class="delivery--edit-icon text-primary" id="delivery_address" data-toggle="modal"
                                        data-target="#paymentModal"><i class="tio-edit"></i></span>
                                    @endif
                                    
                                </div>
                                <div class="pos--delivery-options-info d-flex flex-wrap" id="del-add">
                                    @include('vendor-views.pos._address')
                                </div>
                            </div>
                        @endif
                    </div>  
                    <!-- <input type="hidden" name="partner_id" value="{{ $orderPartner }}" > -->

                    <div class='w-100' id="cart">
                        @include('vendor-views.pos._cart',['orderPartner' => $orderPartner])
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="quick-view" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" id="quick-view-modal">

                </div>
            </div>
        </div>
        @php($order = Order::find(session('last_order')))
        @if ($order)
            @php(session(['last_order' => false]))

            {{-- Load Bill Print --}}
            <div id="bill-print-content" class="d-none">
                @include('new_invoice', ['order' => $order])
            </div>

            {{-- Load Kitchen Print --}}
            <div id="kitchen-print-content" class="d-none">
                @include('kitchen_receipt', ['order' => $order])
            </div>

            {{-- <div class="modal fade" id="print-invoice" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ translate('messages.print_invoice') }}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body pt-0 row ff-emoji">

                            <div class="col-12" id="printableArea">
                                @include('new_invoice')
                            </div>

                        </div>
                    </div>
                </div>
            </div> --}}
        @endif


        <!-- Static Delivery Address Modal -->
        <div class="modal fade" id="delivery-address">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light border-bottom py-3">
                        <h3 class="modal-title flex-grow-1 text-center">{{ translate('Delivery Options') }}</h3>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="contact_person_name"
                                        class="input-label">{{ translate('Contact person name') }}</label>
                                    <input id="contact_person_name" type="text" class="form-control"
                                        name="contact_person_name" value=""
                                        placeholder="{{ translate('messages.Ex :') }} Jhone">
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_person_number"
                                        class="input-label">{{ translate('Contact Number') }}</label>
                                    <input id="contact_person_number" type="text" class="form-control"
                                        name="contact_person_number" value=""
                                        placeholder="{{ translate('messages.Ex :') }} +3264124565">
                                </div>
                                <div class="col-md-4">
                                    <label for="road" class="input-label">{{ translate('Road') }}</label>
                                    <input id="road" type="text" class="form-control" name="road"
                                        value="" placeholder="{{ translate('messages.Ex :') }} 4th">
                                </div>
                                <div class="col-md-4">
                                    <label for="house" class="input-label">{{ translate('House') }}</label>
                                    <input id="house" type="text" class="form-control" name="house"
                                        value="" placeholder="{{ translate('messages.Ex :') }} 45/C">
                                </div>
                                <div class="col-md-4">
                                    <label for="floor" class="input-label">{{ translate('Floor') }}</label>
                                    <input id="floor" type="text" class="form-control" name="floor"
                                        value="" placeholder="{{ translate('messages.Ex :') }} 1A">
                                </div>

                                <div class="col-md-12">
                                    <label for="address" class="input-label">{{ translate('Address') }}</label>
                                    <textarea id="address" name="address" class="form-control" cols="30" rows="3"
                                        placeholder="{{ translate('messages.Ex :') }} address"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3 h-200px" id="map"></div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end">
                                <button class="btn btn-sm btn--primary w-100" type="submit">
                                    {{ translate('Update Delivery address') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Static Delivery Address Modal -->

        <!-- Add Customer Modal -->
        <div class="modal fade" id="add-customer" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light py-3">
                        <h4 class="modal-title">{{ translate('add_new_customer') }}</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('vendor.pos.customer-store') }}" method="post" id="product_form">
                            @csrf
                            <div class="row pl-2">
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="f_name" class="input-label">{{ translate('Customer Name') }} <span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="f_name" type="text" name="f_name" class="form-control"
                                            value="{{ old('f_name') }}" placeholder="{{ translate('Customer Name') }}"
                                            required>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="phone" class="input-label">{{ translate('phone') }}<span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="phone" type="tel" name="phone" class="form-control"
                                            value="{{ old('phone') }}" placeholder="{{ translate('phone') }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row pl-2">
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="email" class="input-label">{{ translate('email') }}</label>
                                        <input id="email" type="email" name="email" class="form-control"
                                            value="{{ old('email') }}"
                                            placeholder="{{ translate('Ex_:_ex@example.com') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{ translate('reset') }}</button>
                                <button type="button" id="submit_new_customer"
                                    class="btn btn--primary">{{ translate('save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ BusinessSetting::where('key', 'map_api_key')->first()->value }}&libraries=places,geometry&callback=initMap&v=3.49">
    </script>
    <script src="{{ dynamicAsset('public/assets/admin/js/view-pages/pos.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/restaurant_panel/qz-tray.js') }}"></script>
    <script>
        "use strict";
        let itemQty = 0;
        const channel = new BroadcastChannel("erp_tab_channel");
        let currentUrl = window.location.pathname;

        // Extract numeric ID (if exists)
        const idMatch = currentUrl.match(/\/(\d+)$/);
        const currentId = idMatch ? idMatch[1] : null;

        // Normalize URL (remove numeric ID)
        const baseUrl = currentUrl.replace(/\/\d+$/, "");

        let isDuplicate = false;

        channel.postMessage({ type: "CHECK_URL", url: baseUrl, id: currentId });

        channel.onmessage = (event) => {
            const { type, url, id } = event.data;

            if (type === "CHECK_URL" && url === baseUrl) {
                // Same page and same ID → block
                isDuplicate = true;
                alert("This POS page is already open in another tab.");
                window.location.href = "/404";
            }
        };

        setTimeout(() => {
            if (!isDuplicate) {
                channel.postMessage({ type: "REGISTER_URL", url: baseUrl, id: currentId });
            }
        }, 500);

        window.addEventListener("beforeunload", () => {
            channel.postMessage({ type: "UNREGISTER_URL", url: baseUrl, id: currentId });
        });

        function initMap() {
            let map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13,
                center: {
                    lat: {{ $restaurant_data ? $restaurant_data['latitude'] : '23.757989' }},
                    lng: {{ $restaurant_data ? $restaurant_data['longitude'] : '90.360587' }}
                }
            });
            let zonePolygon = null;
            let infoWindow = new google.maps.InfoWindow();
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        myLatlng = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        infoWindow.setPosition(myLatlng);
                        infoWindow.setContent("{{ translate('Location_found') }}");
                        infoWindow.open(map);
                        map.setCenter(myLatlng);
                    },
                    () => {
                        handleLocationError(true, infoWindow, map.getCenter());
                    }
                );
            } else {
                handleLocationError(false, infoWindow, map.getCenter());
            }
            const input = document.getElementById("pac-input");
            if (!input) {
                console.log("pac-input element not found");
                return;
            }
            const searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
            let markers = [];
            const bounds = new google.maps.LatLngBounds();
            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();

                if (places.length === 0) {
                    return;
                }
                markers.forEach((marker) => {
                    marker.setMap(null);
                });
                markers = [];
                places.forEach((place) => {
                    if (!place.geometry || !place.geometry.location) {
                        console.log("Returned place contains no geometry");
                        return;
                    }
                    console.log(place.geometry.location);

                    // Check if zone polygon is loaded before validating location
                    if (zonePolygon) {
                        if (!google.maps.geometry.poly.containsLocation(
                                place.geometry.location,
                                zonePolygon
                            )) {
                            toastr.error('{{ translate('messages.out_of_coverage') }}', {
                                CloseButton: true,
                                ProgressBar: true
                            });
                            return false;
                        }
                    } else {
                        console.log("Zone polygon not loaded yet, skipping coverage check");
                    }
                    document.getElementById('latitude').value = place.geometry.location.lat();
                    document.getElementById('longitude').value = place.geometry.location.lng();
                    const icon = {
                        url: place.icon,
                        size: new google.maps.Size(71, 71),
                        origin: new google.maps.Point(0, 0),
                        anchor: new google.maps.Point(17, 34),
                        scaledSize: new google.maps.Size(25, 25),
                    };
                    markers.push(
                        new google.maps.Marker({
                            map,
                            icon,
                            title: place.name,
                            position: place.geometry.location,
                        })
                    );

                    if (place.geometry.viewport) {
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                });
                map.fitBounds(bounds);
            });
            @if ($restaurant_data)
                $.get({
                    url: '{{ url('/') }}/admin/zone/get-coordinates/{{ $restaurant_data->zone_id }}',
                    dataType: 'json',
                    success: function(data) {
                        zonePolygon = new google.maps.Polygon({
                            paths: data.coordinates,
                            strokeColor: "#FF0000",
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: 'white',
                            fillOpacity: 0,
                        });
                        zonePolygon.setMap(map);
                        zonePolygon.getPaths().forEach(function(path) {
                            path.forEach(function(latlng) {
                                bounds.extend(latlng);
                                map.fitBounds(bounds);
                            });
                        });
                        map.setCenter(data.center);
                        google.maps.event.addListener(zonePolygon, 'click', function(mapsMouseEvent) {
                            infoWindow.close();
                            infoWindow = new google.maps.InfoWindow({
                                position: mapsMouseEvent.latLng,
                                content: JSON.stringify(mapsMouseEvent.latLng.toJSON(), null,
                                    2),
                            });
                            let coordinates;
                            coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                            coordinates = JSON.parse(coordinates);

                            document.getElementById('latitude').value = coordinates['lat'];
                            document.getElementById('longitude').value = coordinates['lng'];
                            infoWindow.open(map);
                            let geocoder;
                            geocoder = geocoder = new google.maps.Geocoder();
                            let latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);

                            geocoder.geocode({
                                'latLng': latlng
                            }, function(results, status) {
                                if (status === google.maps.GeocoderStatus.OK) {
                                    if (results[1]) {
                                        let address = results[1].formatted_address;

                                        const geocoder = new google.maps.Geocoder();
                                        const service = new google.maps.DistanceMatrixService();

                                        const origin1 = {
                                            lat: {{ $restaurant_data['latitude'] }},
                                            lng: {{ $restaurant_data['longitude'] }}
                                        };
                                        const origin2 = "{{ $restaurant_data->address }}";
                                        const destinationA = address;
                                        const destinationB = {
                                            lat: coordinates['lat'],
                                            lng: coordinates['lng']
                                        };
                                        const request = {
                                            origins: [origin1, origin2],
                                            destinations: [destinationA, destinationB],
                                            travelMode: google.maps.TravelMode.DRIVING,
                                            unitSystem: google.maps.UnitSystem.METRIC,
                                            avoidHighways: false,
                                            avoidTolls: false,
                                        };

                                        service.getDistanceMatrix(request).then((response) => {
                                            let distancMeter = response.rows[0]
                                                .elements[0].distance['value'];
                                            let distanceMile = distancMeter / 1000;
                                            let distancMileResult = Math.round((
                                                    distanceMile + Number.EPSILON) *
                                                100) / 100;
                                            console.log(distancMileResult);
                                            document.getElementById('distance').value =
                                                distancMileResult;
                                            <?php
                                            $rest_sub = $restaurant_data->restaurant_sub;
                                            if (($restaurant_data->restaurant_model == 'commission' && $restaurant_data->self_delivery_system == 1) || ($restaurant_data->restaurant_model == 'subscription' && isset($rest_sub) && $rest_sub->self_delivery == 1)) {
                                                $per_km_shipping_charge = (float) $restaurant_data->per_km_shipping_charge;
                                                $minimum_shipping_charge = (float) $restaurant_data->minimum_shipping_charge;
                                                $maximum_shipping_charge = (float) $restaurant_data->maximum_shipping_charge;
                                                $increased = 0;
                                                $self_delivery_status = 1;
                                            } else {
                                                $per_km_shipping_charge = $restaurant_data->zone->per_km_shipping_charge ?? 0;
                                                $minimum_shipping_charge = $restaurant_data->zone->minimum_shipping_charge ?? 0;
                                                $maximum_shipping_charge = $restaurant_data->zone->maximum_shipping_charge ?? 0;
                                                $increased = 0;
                                                if ($restaurant_data->zone->increased_delivery_fee_status == 1) {
                                                    $increased = $restaurant_data->zone->increased_delivery_fee ?? 0;
                                                }
                                                $self_delivery_status = 0;
                                            }
                                            ?>

                                            $.get({
                                                url: '{{ route('vendor.pos.extra_charge') }}',
                                                dataType: 'json',
                                                data: {
                                                    distancMileResult: distancMileResult,
                                                    self_delivery_status: {{ $self_delivery_status }},
                                                },
                                                success: function(data) {
                                                    let extra_charge = data;
                                                    let original_delivery_charge =
                                                        (distancMileResult *
                                                            {{ $per_km_shipping_charge }} >
                                                            {{ $minimum_shipping_charge }}
                                                        ) ?
                                                        distancMileResult *
                                                        {{ $per_km_shipping_charge }} :
                                                        {{ $minimum_shipping_charge }};
                                                    let delivery_amount = (
                                                        {{ $maximum_shipping_charge }} >
                                                        {{ $minimum_shipping_charge }} &&
                                                        original_delivery_charge +
                                                        extra_charge >
                                                        {{ $maximum_shipping_charge }} ?
                                                        {{ $maximum_shipping_charge }} :
                                                        original_delivery_charge +
                                                        extra_charge);
                                                    let with_increased_fee =
                                                        (delivery_amount *
                                                            {{ $increased }}
                                                        ) / 100;
                                                    let delivery_charge =
                                                        Math.round((
                                                                delivery_amount +
                                                                with_increased_fee +
                                                                Number
                                                                .EPSILON) *
                                                            100) / 100;
                                                    document.getElementById(
                                                            'delivery_fee')
                                                        .value =
                                                        delivery_charge;
                                                    $('#delivery_fee')
                                                        .siblings('strong')
                                                        .html(
                                                            delivery_charge +
                                                            ' ({{ Helpers::currency_symbol() }})'
                                                        );

                                                },
                                                error: function() {
                                                    let original_delivery_charge =
                                                        (distancMileResult *
                                                            {{ $per_km_shipping_charge }} >
                                                            {{ $minimum_shipping_charge }}
                                                        ) ?
                                                        distancMileResult *
                                                        {{ $per_km_shipping_charge }} :
                                                        {{ $minimum_shipping_charge }};

                                                    let delivery_charge =
                                                        Math.round((
                                                                ({{ $maximum_shipping_charge }} >
                                                                    {{ $minimum_shipping_charge }} &&
                                                                    original_delivery_charge >
                                                                    {{ $maximum_shipping_charge }} ?
                                                                    {{ $maximum_shipping_charge }} :
                                                                    original_delivery_charge
                                                                ) +
                                                                Number
                                                                .EPSILON) *
                                                            100) / 100;
                                                    document.getElementById(
                                                            'delivery_fee')
                                                        .value =
                                                        delivery_charge;
                                                    $('#delivery_fee')
                                                        .siblings('strong')
                                                        .html(
                                                            delivery_charge +
                                                            ' ({{ Helpers::currency_symbol() }})'
                                                        );
                                                }
                                            });

                                        });
                                    }
                                }
                            });
                        });
                    },
                });
            @endif

        }

        function handleLocationError(browserHasGeolocation, infoWindow, pos) {
            infoWindow.setPosition(pos);
            infoWindow.setContent(
                browserHasGeolocation ?
                "Error: {{ translate('The Geolocation service failed') }}." :
                "Error: {{ translate('Your browser does not support geolocation') }}."
            );
            infoWindow.open(map);
        }


        $(document).on('click', '.order_partner', function() {
            $('#loading').show();
            var selectedId = $(this).data('value'); // capture before AJAX

            $.post('{{ route('vendor.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }).done(function() {
                window.location.href = '/restaurant-panel/pos/new/' + selectedId;
                // Clear the customer from local storage
                localStorage.removeItem('posSelectedCustomer');
                $('#customer').val(null).trigger('change');
            }).always(function() {
                $('#loading').hide();
            });
        });


        $("#insertPayableAmount").on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        })

        $(document).on('ready', function() {
            @if ($order)
                $('#print-invoice').modal('show');
            @endif
            
        });

        $(document).on('click', '#orderFinalModal_btn', function(e) {
            @if (!empty($orderPartner))
                $('#payment_type_credit').prop('checked', true);
                $('.payment_type').prop('disabled', true);
                $('<input>').attr({
                    type: 'hidden',
                    name: 'select_payment_type',
                    value: 'credit_payment'
                }).appendTo('#order_place');
                $('#cash_paid, #card_paid, #customer_name, #car_number, #phone')
                .prop('readonly', true);
                // Disable the Unpaid Order Button
                $('#unpaidOrderBtn').hide();
            @else
                $('#payment_type_credit_wrapper').remove();
               if($('input[name="select_payment_type"]:checked').length > 0){
                    $('input[name="select_payment_type"]').prop('checked', false);
                    $('#cash_paid, #card_paid').prop('readOnly', true).val('').trigger('input');
                }  
                $('.payment_type').prop('disabled', false);
                $('#unpaidOrderBtn').prop('disabled', false);
            @endif   
            


            if(window.updateDate && !window.editingOrder){
                // Show orderFianlModal
                Swal.fire({
                    type: "warning",
                    title: "Date does not matched!",
                    text: "Session Date is {{ \Carbon\Carbon::parse($orderDate)->format('d F, Y') }}",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Change Date",
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    preConfirm: () => {
                        return new Promise((resolve) => {
                            resolve(true);
                        });
                    }
                }).then((result) => {
                    if (result.value) {
                        // setTimeout(() => {
                        //     setTimeout(() => { if (typeof window.fillOrderModal === 'function') window.fillOrderModal(); }, 500);
                        //     $('#orderFinalModal').modal('show');
                        // }, 100);
                        window.location.href = '{{ route("vendor.printer.selection") }}';
                    }
                });
            }else{
                setTimeout(() => { if (typeof window.fillOrderModal === 'function') window.fillOrderModal(); }, 500);
                $('#orderFinalModal').modal('show');
            }            
        });


        $('#search-form').on('submit', function(e) {
            e.preventDefault();
            let keyword = $('#datatableSearch').val();
            let nurl = new URL('{!! url()->full() !!}');
            nurl.searchParams.set('keyword', keyword);
            location.href = nurl;
        });


        $(document).on('click', '.quick-View', function() {
            itemQty = 0;
            $.get({
                url: '{{ route("vendor.pos.quick-view") }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id'),
                    id: '{{ $orderPartner ?? '' }}',
                },
                beforeSend: function() {
                    // $('#loading').show();
                },
                success: function(data) {
                    console.log("success...")
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    // $('#loading').hide();
                },
            });
        });

        $(document).on('click', '.quick-View-Cart-Item', function() {
            itemQty = $(this).data('item-qty');
            $.get({
                url: '{{ route("vendor.pos.quick-view-cart-item") }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('product-id'),
                    item_key: $(this).data('item-key'),
                },
                beforeSend: function() {
                    // $('#loading').show();
                },
                success: function(data) {
                    console.log("success...")
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    // $('#loading').hide();
                },
            });
        });



        // function getVariantPrice() {
        //     getCheckedInputs();
        //     if ($('#add-to-cart-form input[name=quantity]').val() > 0) {
        //         $.ajaxSetup({
        //             headers: {
        //                 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
        //             }
        //         });
        //         $.ajax({
        //             type: "POST",
        //             url: '{{ route('vendor.pos.variant_price') }}',
        //             data: $('#add-to-cart-form').serializeArray(),
        //             success: function(data) {
        //                 if (data.error === 'quantity_error') {
        //                     toastr.error(data.message);
        //                 } else if (data.error === 'stock_out') {
        //                     toastr.warning(data.message);
        //                     if (data.type == 'addon') {
        //                         $('#addon_quantity_button' + data.id).attr("disabled", true);
        //                         $('#addon_quantity_input' + data.id).val(data.current_stock);
        //                     } else {
        //                         $('#quantity_increase_button').attr("disabled", true);
        //                         $('#add_new_product_quantity').val(data.current_stock);
        //                     }
        //                     getVariantPrice();
        //                 } else {
        //                     $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
        //                     $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
        //                     $('.add-To-Cart').removeAttr("disabled");
        //                     $('.increase-button').removeAttr("disabled");
        //                     $('#quantity_increase_button').removeAttr("disabled");

        //                 }
        //             }
        //         });
        //     }
        // }

        var getVariantPriceRequest = null;
        function getVariantPrice() {
            getCheckedInputs();

            var discountAmount = $('#product_discount').val() || 0;
            var discountType = $('#product_discount_type').val();

            var formData = $('#add-to-cart-form').serializeArray();
            
            var variationAddonData = {};
            formData.forEach(function(item) {
                if (item.name.startsWith('variation_addon_')) {
                    if (!variationAddonData[item.name]) {
                        variationAddonData[item.name] = [];
                    }
                    variationAddonData[item.name].push(item.value);
                }
            });
            console.log('Variation addon data:', variationAddonData);

            if ($('#add-to-cart-form input[name=quantity]').val() > 0) {

                if (getVariantPriceRequest !== null) {
                    getVariantPriceRequest.abort();
                }

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });

                getVariantPriceRequest = $.ajax({
                    type: "POST",
                    url: '{{ route('vendor.pos.variant_price') }}',
                    data: $('#add-to-cart-form').serializeArray().concat([{
                            name: 'product_discount',
                            value: discountAmount
                        },
                        {
                            name: 'product_discount_type',
                            value: discountType
                        }
                    ]), // Include discount values explicitly
                    success: function(data) {
                        console.log('Server response from variant_price:', data);
                        if (data.error === 'quantity_error') {
                            toastr.error(data.message);
                        } else if (data.error === 'stock_out') {
                            toastr.warning(data.message);
                            if (data.type == 'addon') {
                                $('#addon_quantity_button' + data.id).attr("disabled", true);
                                $('#addon_quantity_input' + data.id).val(data.current_stock);
                            } else {
                                $('#quantity_increase_button').attr("disabled", true);
                                $('#add_new_product_quantity').val(data.current_stock);
                            }
                            getVariantPrice();
                        } else {
                            console.log('in else part');
                            var currentDiscountAmount = parseFloat($('#set-discount-amount').text().replace(
                                /[^0-9.-]+/g, '').trim()) || 0;
                            var currentPrice = data.price;
                            discountAmount = parseFloat(discountAmount) || 0;
                            $('#product-price').html(data.pre_addon_price);
                            // if ((currentDiscountAmount !== discountAmount) && discountAmount !== 0) {
                            $('#original-price').removeClass('d-none').html(data.original_price);
                            // }
                            if (discountAmount == 0) {
                                $('#original-price').addClass('d-none');
                            }
                            // if (currentDiscountAmount !== discountAmount) {
                            if (discountType === 'percent') {
                                discountAmount = discountAmount + ' %';
                            }
                            $('#set-discount-amount').html(discountAmount);
                            // }

                            // Update the price display
                            $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                            $('#add-to-cart-form #chosen_price_div #chosen_price').html(currentPrice);
                            $('.add-To-Cart').removeAttr("disabled");
                            $('.increase-button').removeAttr("disabled");

                            $('#quantity_increase_button').prop('disabled', $('#quantity_increase_button').data('editing') == 1);
                        }
                    },
                    error: function(xhr, status) {
                        if (status === "abort") return; // ignore aborted requests
                        toastr.error('Something went wrong. Please try again.');
                    }
                });
            }
        }

        function sendAddToCartRequest(form_id, button) {
            $.post({
                url: '{{ route("vendor.pos.add-to-cart") }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    // $('#loading').show();
                },
                success: function(data) {
                    if (data.data === 1) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Cart',
                            text: "{{ translate('messages.product_already_added_in_cart') }}"
                        });
                        return false;
                    } else if (data.data === 2) {
                        updateCart();
                        toastr.success(
                        '{{ translate('messages.product_has_been_updated_in_cart') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        $('.call-when-done').click();
                        return false;
                    } else if (data.data === 'stock_out') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cart',
                            text: data.message
                        });
                        return false;
                    }else if(data.data === 'not_allowed'){
                        Swal.fire({
                            type: 'error',
                            title: 'Order Is Paid',
                            text: '{{ translate('messages.you_can_not_add_more_item_in_paid_order') }}'
                        });
                        return false;
                    } else if (data.data === 'cart_readded') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cart',
                            text: "{{ translate('messages.product_quantity_updated_in_cart') }}"
                        });
                        updateCart();
                        return false;
                    } else if (data.data === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cart',
                            text: '{{ translate('messages.Sorry, product out of stock') }}'
                        });
                        return false;
                    } else if (data.data === 'variation_error') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cart',
                            text: data.message
                        });
                        return false;
                    } else if (data.data === 'price_updation_error') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Price Is Reduced',
                            text: data.message
                        });
                        return false;
                    }
                    $('.call-when-done').click();

                    toastr.success('{{ translate('messages.product_has_been_added_in_cart') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });

                    updateCart();
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '{{ translate('messages.something_went_wrong') }}',
                    });
                },
                complete: function() {
                    // $('#loading').hide();
                    isProcessing = false;
                    button.prop('disabled', false);
                }
            });
        }

        let isProcessing = false;
        $(document).on('click', '.add-To-Cart', function() {
            if (isProcessing) return;
            isProcessing = true;
            const button = $(this);
            button.prop('disabled', true);

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            let form_id = 'add-to-cart-form';
            // Get the new quantity value
            let newQuantity = parseInt($('#add-to-cart-form input[name=quantity]').val());
            if(newQuantity < itemQty && window.editingOrder){
                // Show confirmation dialog and get the cancel reason, cooking status, and text
                $('#quick-view').modal('hide');
                Swal.fire({
                    title: '{{ translate('messages.are_you_sure?') }}',
                    icon: 'warning',
                    html: `
                        <div style="text-align:left">
                            <select class="form-control js-select2-custom mx-0" name="item-reduce-reason" id="item-reduce-reason">
                                <option value="" selected disabled>
                                    {{ translate('select_cancellation_reason') }}
                                </option>
                                @foreach ($reasons as $r)
                                    <option value="{{ $r->id }}">
                                        {{ $r->reason }}
                                    </option>
                                @endforeach
                            </select>

                            <div style="margin-top:10px;">
                                <label style="font-size:14px;">Item Status:</label><br>
                                <div class="d-flex gap-2 mt-2 justify-content-between">
                                    <label class="payment-selection-box w-100">
                                        <input type="radio" name="item-reduce-status" value="1" class="mr-1"> {{ translate('messages.Unprepared') }}
                                    </label>
                                    <label class="payment-selection-box w-100">
                                        <input type="radio" name="item-reduce-status" value="2" class="mr-1"> {{ translate('messages.Re-sold') }}
                                    </label>
                                    <label class="payment-selection-box w-100">
                                        <input type="radio" name="item-reduce-status" value="3" class="mr-1"> {{ translate('messages.Wasted') }}
                                    </label>
                                </div>
                            </div>

                            <div style="margin-top:10px;">
                                <label style="font-size:14px;">Enter Reason:</label><br>
                                <input type="text" class="form-control" id="item-reduce-text" name="item-reduce-text" />
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '{{ translate('messages.Yes, Proceed!') }}',
                    cancelButtonText: '{{ translate('messages.Back') }}',
                    preConfirm: () => {
                        const dropdown = document.getElementById('item-reduce-reason').value;
                        const radio    = document.querySelector('input[name="item-reduce-status"]:checked');
                        const text     = document.getElementById('item-reduce-text').value;

                        if (!dropdown) {
                            Swal.showValidationMessage('Please select cancel reason');
                            return false;
                        }

                        if (!radio) {
                            Swal.showValidationMessage('Please select item cooking status');
                            return false;
                        }

                        return {
                            cancelReason: dropdown,
                            cookingStatus: radio.value,
                            cancelText: text
                        };
                    }
                }).then((result) => {
                    if (result.value) {
                        const { cancelReason, cookingStatus, cancelText } = result.value;
                        const $form = $('#' + form_id);

                        if (!$form.find('input[name="cart_item_cancel_reason"]').length) {
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'cart_item_cancel_reason',
                                value: cancelReason
                            }).appendTo($form);
                        } else {
                            $form.find('input[name="cart_item_cancel_reason"]').val(cancelReason);
                        }

                        if (!$form.find('input[name="cart_item_cooking_status"]').length) {
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'cart_item_cooking_status',
                                value: cookingStatus
                            }).appendTo($form);
                        } else {
                            $form.find('input[name="cart_item_cooking_status"]').val(cookingStatus);
                        }

                        if (!$form.find('input[name="cart_item_cancel_text"]').length) {
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'cart_item_cancel_text',
                                value: cancelText
                            }).appendTo($form);
                        } else {
                            $form.find('input[name="cart_item_cancel_text"]').val(cancelText);
                        }

                        sendAddToCartRequest(form_id, button);
                    } else {
                        isProcessing = false;
                        button.prop('disabled', false);
                    }
                });
            }else{
                sendAddToCartRequest(form_id, button);
            }
        });

        $(document).on('click', '.remove-From-Cart', function() {
            let key = $(this).data('product-id');
            if(window.editingOrder){
                Swal.fire({
                title: '{{ translate('messages.are_you_sure?') }}',
                type: 'warning',
                html: `
                    <div style="text-align:left">
                        <select class="form-control js-select2-custom mx-0" name="item-cancel-reason" id="item-cancel-reason">
                            <option value="" selected disabled>
                                {{ translate('select_cancellation_reason') }}
                            </option>
                            @foreach ($reasons as $r)
                                <option value="{{ $r->id }}">
                                    {{ $r->reason }}
                                </option>
                            @endforeach
                        </select>

                        <div style="margin-top:10px;">
                            <label style="font-size:14px;">Cooking Status:</label><br>
                            <div class="d-flex gap-2 mt-2 justify-content-between">
                                <label class="payment-selection-box w-100">
                                    <input type="radio" name="item-cooking-status" value="1" class="mr-1"> {{translate('messages.Unprepared')}}
                                </label>
                                <label class="payment-selection-box w-100">
                                    <input type="radio" name="item-cooking-status" value="2" class="mr-1"> {{translate('messages.Re-sold')}}
                                </label>
                                <label class="payment-selection-box w-100">
                                    <input type="radio" name="item-cooking-status" value="3" class="mr-1"> {{translate('messages.Wasted')}}
                                </label>
                            </div>
                        </div>

                        <div style="margin-top:10px;">
                            <label style="font-size:14px;">Enter Reason:</label><br>
                            <input type="text" class="form-control" id="item-cancel-text" name="item-cancel-text" />
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '{{ translate('messages.Cancel Item') }}',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: 'Back',
                focusConfirm: false,
                preConfirm: () => {
                    const dropdown = document.getElementById('item-cancel-reason').value;
                    const radio    = document.querySelector('input[name="item-cooking-status"]:checked');
                    const text     = document.getElementById('item-cancel-text').value;

                    if (!dropdown) {
                        Swal.showValidationMessage('Please select cancel reason');
                        return false;
                    }

                    if (!radio) {
                        Swal.showValidationMessage('Please select item cooking status');
                        return false;
                    }

                    return {
                        cancelReason: dropdown,
                        cookingStatus: radio.value,
                        cancelText: text
                    };
                }
                }).then(result => {
                    if (result.value) {
                        const { cancelReason, cookingStatus, cancelText } = result.value;

                        $.post('{{ route('vendor.pos.remove-from-cart') }}', {
                            _token: '{{ csrf_token() }}',
                            key: key,
                            cancelReason: cancelReason,
                            cookingStatus: cookingStatus,
                            cancelText: cancelText,
                            beforeSend: function(){
                                // $('#loading').show();
                            },
                        }, function(data) {
                            if (data.errors) {
                                for (let i = 0; i < data.errors.length; i++) {
                                    toastr.error(data.errors[i].message, {
                                        CloseButton: true,
                                        ProgressBar: true
                                    });
                                }
                            } else {
                                $('#quick-view').modal('hide');
                                updateCart();
                                toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                                    CloseButton: true,
                                    ProgressBar: true
                                });
                            }
                            // $('#loading').hide();
                        });
                    }
                });
            }
            else{
                $.post('{{ route('vendor.pos.remove-from-cart') }}', {
                    _token: '{{ csrf_token() }}',
                    key: key,
                    beforeSend: function(){
                        // $('#loading').show();
                    },
                }, function(data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $('#quick-view').modal('hide');
                        updateCart();
                        toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                    // $('#loading').hide();
                });
            }
        });

        $(document).on('click', '.empty-Cart', function() {
            $.post('{{ route('vendor.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }, function() {
                $('#del-add').empty();
                // Clear Select2 customer
                $('#customer')
                .val(null)
                .trigger('change.select2');
                localStorage.removeItem('posSelectedCustomer');
                window.selectedCustomer = null;
                window.updateDate = null;
                window.editingOrder = false;
                updateCart();
                if($('#editingOrderHeading').length){
                    $('#editingOrderHeading').html('');
                }

                $('#newOrderHeading').removeClass('d-none');

                toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            });
        });

        function updateCart() {
            const currentCustomerId   = window.selectedCustomer;
            const currentCustomerText = null;

            $.ajax({
                url: '{{ route('vendor.pos.cart_items') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    partner_id: '{{ $orderPartner ?? '' }}'
                },
                beforeSend: function () {
                    // $('#loading').show();
                },
                success: function (data) {
                    $('#cart').html(data);

                    // ✅ Restore ONLY if a customer is explicitly set
                    if (window.selectedCustomer) {
                        $('#customer')
                            .val(window.selectedCustomer)
                            .trigger('change.select2');

                        if ($('#orderFinalModal').is(':visible')) {
                            tryFillModalWithRetries(5, 100);
                        }
                    }
                },
                complete: function () {
                    // $('#loading').hide();
                },
                error: function () {
                    // $('#loading').hide();
                    // Optional: show toast
                    // toastr.error('Failed to update cart');
                }
            });
        }


        $(document).on('click', '.delivery-Address-Store', function() {
            const button = $(this);

            if (button.prop('disabled')) {
                return false;
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            let form_id = 'delivery_address_store';
            $.post({
                url: '{{ route('vendor.pos.add-delivery-info') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    // $('#loading').show();
                    button.prop('disabled', true);
                    if (!button.data('original-text')) {
                        button.data('original-text', button.html());
                    }
                    button.html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Wait');
                },
                success: function(data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $('#del-add').empty().html(data.view);
                    }
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    // $('#loading').hide();
                    $('#paymentModal').modal('hide');

                    button.prop('disabled', false);
                    button.html(button.data('original-text'));
                },
                error: function() {
                    button.prop('disabled', false);
                    button.html(button.data('original-text'));
                }
            });
        });

        $(document).on('click', '.payable-Amount', function() {
            const button = $(this);

            if (button.prop('disabled')) {
                return false;
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            let form_id = 'payable_store_amount';
            $.post({
                url: '{{ route('vendor.pos.paid') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    // $('#loading').show();
                    button.prop('disabled', true);
                    if (!button.data('original-text')) {
                        button.data('original-text', button.html());
                    }
                    button.html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Wait');
                },
                success: function() {
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    // $('#loading').hide();
                    $('#insertPayableAmount').modal('hide');

                    button.prop('disabled', false);
                    button.html(button.data('original-text'));
                },
                error: function() {
                    button.prop('disabled', false);
                    button.html(button.data('original-text'));
                }
            });
        });

        $(document).on('change', '[name="quantity"]', function(event) {
            // getVariantPrice();
            calculateTotal();
            if ($('#option_ids').val() == '') {
                $(this).attr('max', $(this).data('maximum_cart_quantity'));
            }
        });

        $(document).on('change', '.update-Quantity', function(event) {
            let element = $(event.target);
            let minValue = parseInt(element.attr('min'));
            let maxValue = parseInt(element.attr('max'));
            let valueCurrent = parseInt(element.val());
            let option_ids = element.data('option_ids');
            let food_id = element.data('food_id');
            let key = element.data('key');
            let oldvalue = element.data('value');
            if (valueCurrent >= minValue && maxValue >= valueCurrent) {
                $.post('{{ route('vendor.pos.updateQuantity') }}', {
                    _token: '{{ csrf_token() }}',
                    key: key,
                    food_id: food_id,
                    option_ids: option_ids,
                    quantity: valueCurrent
                }, function(data) {
                    if (data.data == 'stock_out') {
                        element.val(oldvalue);
                        Swal.fire({
                            icon: 'error',
                            title: "{{ translate('Cart') }}",
                            text: data.message
                        });
                    } else {
                        updateCart();
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: "{{ translate('Cart') }}",
                    text: "{{ translate('quantity_unavailable') }}"
                });
                element.val(oldvalue);
            }
            if (event.type === 'keydown') {
                if ($.inArray(event.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (event.keyCode === 65 && event.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (event.keyCode >= 35 && event.keyCode <= 39)) {
                    return;
                }
                if ((event.shiftKey || (event.keyCode < 48 || event.keyCode > 57)) && (event.keyCode < 96 || event
                        .keyCode > 105)) {
                    event.preventDefault();
                }
            }
        });

        $('.js-data-example-ajax').select2({
            ajax: {
                url: '{{ route('vendor.pos.customers') }}',
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

        @if(isset($draftCustomer) && $draftCustomer)
            $(document).ready(function() {
                let customerId = '{{ $draftCustomer->customer_id }}';
                let customerName = '{{ $draftCustomer->customer_name }}';
                let customerPhone = '{{ $draftCustomer->customer_mobile_no }}';
                let customerText = customerName + ' (' + customerPhone + ')';

                const $customer = $('#customer');
                // Prevent duplicates
                if ($customer.find('option[value="' + customerId + '"]').length === 0) {
                    $customer.append(new Option(customerText, customerId, true, true));
                }

                // Ensure "Walk in customer" has a real value (e.g. empty string)
                // if ($customer.find('option[value=""]').length === 0) {
                //     $customer.prepend(new Option('Walk in customer', '', false, false));
                // }

                // Set selected once (this triggers change once)
                $customer.val(customerId).trigger('change');

                // If storeCustomerDetails triggers change internally, DON'T call it here
                // Otherwise keep it:
                storeCustomerDetails(customerId, customerText);

                console.log('Draft order customer auto-filled: ' + customerText);
            });
        @else
            $(document).ready(function() {
                // restoreCustomerFromStorage();
            });
        @endif

        window.selectedCustomer = null;
        window.updateDate = {{ $updateDate ? 'true' : 'false'  }}; 
        window.editingOrder = {{ $editingOrder ? 'true' : 'false' }};

        if(!window.editingOrder){
            $('#newOrderHeading').removeClass('d-none');
            localStorage.removeItem("posSelectedCustomer");
        }

        function parseCustomerData(customerId, customerText) {
            if (!customerId || customerId === 'false' || !customerText) {
                return null;
            }

            let match = customerText.match(/^(.+?)\s*\((.+?)\)$/);
            if (match) {
                return {
                    id: customerId,
                    name: match[1].trim(),
                    phone: match[2].trim()
                };
            }
            return null;
        }

        function storeCustomerDetails(customerId, customerText) {
            let customerData = parseCustomerData(customerId, customerText);

            if (customerData) {
                window.selectedCustomer = customerData;

                const customerWithTimestamp = {
                    ...customerData,
                    timestamp: Date.now()
                };
                localStorage.setItem('posSelectedCustomer', JSON.stringify(customerWithTimestamp));

                $('#customer').data('selected-customer', customerData);
            } else {
                window.selectedCustomer = null;
                localStorage.removeItem('posSelectedCustomer');
                $('#customer').val(null).trigger('change');
                $('#customer').removeData('selected-customer');
            }
        }

        function restoreCustomerFromStorage() {
            try {
                const storedCustomer = localStorage.getItem('posSelectedCustomer');
                if (storedCustomer) {
                    const customerData = JSON.parse(storedCustomer);

                    // Check if data is not too old (24 hours)
                    const maxAge = 24 * 60 * 60 * 1000; // 24 hours in milliseconds
                    if (customerData.timestamp && (Date.now() - customerData.timestamp) < maxAge) {

                        const { timestamp, ...customerInfo } = customerData;

                        setTimeout(function() {
                            if ($('#customer').length && typeof $('#customer').select2 === 'function') {

                                const $customer = $('#customer');
                                // Prevent duplicates
                                if ($customer.find('option[value="' + customerId + '"]').length === 0) {
                                    $customer.append(new Option(customerText, customerId, true, true));
                                }

                                // Ensure "Walk in customer" has a real value (e.g. empty string)
                                // if ($customer.find('option[value=""]').length === 0) {
                                //     $customer.prepend(new Option('Walk in customer', '', false, false));
                                // }

                                // Set selected once (this triggers change once)
                                $customer.val(customerId).trigger('change');

                                // If storeCustomerDetails triggers change internally, DON'T call it here
                                // Otherwise keep it:
                                storeCustomerDetails(customerId, customerText);
                            }
                        }, 1000);
                    } else {
                        localStorage.removeItem('posSelectedCustomer');
                        $('#customer').val(null).trigger('change');
                    }
                }
            } catch (e) {
                console.log('Error restoring customer from localStorage:', e);
                localStorage.removeItem('posSelectedCustomer');
                $('#customer').val(null).trigger('change');
            }
        }

        $('#customer').on('select2:select', function(e) {
            let data = e.params.data;
            storeCustomerDetails(data.id, data.text);
            if ($('#paymentModal').hasClass('show') || $('#paymentModal').is(':visible')) {
                setTimeout(() => fillDeliveryAddressModal(), 100);
            }
        });

        $('#customer').on('select2:clear', function(e) {
            storeCustomerDetails(null, null);
            if ($('#paymentModal').hasClass('show') || $('#paymentModal').is(':visible')) {
                setTimeout(() => fillDeliveryAddressModal(), 100);
            }
        });

        $('#customer').on('change', function() {
            let customerId = $(this).val();
            let customerText = $(this).find('option:selected').text();
            storeCustomerDetails(customerId, customerText);
            if ($('#paymentModal').hasClass('show') || $('#paymentModal').is(':visible')) {
                setTimeout(() => fillDeliveryAddressModal(), 100);
            }
        });

        function getCurrentCustomerData() {
            let selectedCustomerId = $('#customer').val();
            let selectedCustomerText = $('#customer').find('option:selected').text();

            if (selectedCustomerId && selectedCustomerId !== 'false') {
                let customerData = parseCustomerData(selectedCustomerId, selectedCustomerText);
                if (customerData) {
                    return customerData;
                }
            }

            if (window.selectedCustomer) {
                return window.selectedCustomer;
            }

            try {
                let storedCustomer = localStorage.getItem('posSelectedCustomer');
                if (storedCustomer) {
                    const customerData = JSON.parse(storedCustomer);
                    const { timestamp, ...customerInfo } = customerData;
                    return customerInfo;
                }
            } catch (e) {
                console.log('Error parsing stored customer data:', e);
            }

            return null;
        }

        function fillModalWithCustomerData() {
            let customerIdField = $('#customer_id');
            let customerNameField = $('#customer_name');
            let phoneField = $('#phone');

            let customerData = getCurrentCustomerData();

            if (customerData && customerData.id && customerData.name && customerData.phone) {
                customerIdField.val(customerData.id);
                customerNameField.val(customerData.name);
                phoneField.val(customerData.phone);
            } else {
                // customerIdField.val('');
                // customerNameField.val('');
                // phoneField.val('');
            }
        }

        function fillDeliveryAddressModal() {
            let contactPersonNameField = $('#contact_person_name');
            let contactPersonNumberField = $('#contact_person_number');

            let customerData = getCurrentCustomerData();

            if (customerData && customerData.name && customerData.phone) {
                contactPersonNameField.val(customerData.name);
                contactPersonNumberField.val(customerData.phone);

                console.log('Delivery address modal filled with customer data: ' + customerData.name);
            } else {
                contactPersonNameField.val('');
                contactPersonNumberField.val('');
            }
        }

        function clearModalFields() {
            $('#customer_id').val('');
            $('#customer_name').val('');
            $('#phone').val('');
        }

        function tryFillModalWithRetries(maxRetries = 5, delay = 200) {
            let attempts = 0;

            function attemptFill() {
                attempts++;

                if ($('#customer_id').length && $('#customer_name').length && $('#phone').length) {
                    fillModalWithCustomerData();
                    return;
                }

                if (attempts < maxRetries) {
                    setTimeout(attemptFill, delay);
                }
            }

            attemptFill();
        }

        $(document).on('shown.bs.modal', '#orderFinalModal', function() {
            setTimeout(() => tryFillModalWithRetries(10, 100), 300);
        });

        $(document).on('hidden.bs.modal', '#orderFinalModal', function() {
            activeInput = null;
        });

        $(document).on('shown.bs.modal', '#paymentModal, #delivery-address', function() {
            setTimeout(() => fillDeliveryAddressModal(), 300);
        });

        $(document).on('DOMNodeInserted', '#orderFinalModal', function() {
            setTimeout(() => tryFillModalWithRetries(5, 100), 100);
        });

        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        const addedNodes = Array.from(mutation.addedNodes);
                        const hasModalContent = addedNodes.some(node =>
                            node.nodeType === 1 &&
                            (node.id === 'orderFinalModal' ||
                             node.querySelector && node.querySelector('#orderFinalModal') ||
                             node.querySelector && node.querySelector('#customer_id'))
                        );

                        if (hasModalContent) {
                            setTimeout(() => tryFillModalWithRetries(5, 100), 200);
                        }
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        window.fillOrderModal = function() {
            tryFillModalWithRetries(10, 100);
        };

        $(document).on('click', '#submit_new_customer', function(e) {
            e.preventDefault();

            const button = $(this);

            if (button.prop('disabled')) {
                return false;
            }

            let form = $('#product_form');
            let formData = form.serialize();

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    button.prop('disabled', true);
                    if (!button.data('original-text')) {
                        button.data('original-text', button.html());
                    }
                    button.html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Saving...');
                },
                success: function(response) {
                    $('#add-customer').modal('hide');

                    form[0].reset();

                    toastr.success('Customer added successfully');

                    $('#customer').select2('destroy');
                    $('.js-data-example-ajax').select2({
                        ajax: {
                            url: '{{ route('vendor.pos.customers') }}',
                            data: function(params) {
                                return {
                                    q: params.term,
                                    page: params.page
                                };
                            },
                            processResults: function(data) {
                                return {
                                    results: data
                                };
                            },
                            __port: function(params, success, failure) {
                                let $request = $.ajax(params);
                                $request.then(success);
                                $request.fail(failure);
                                return $request;
                            }
                        }
                    });

                    $('#customer').off('select2:select change').on('select2:select', function(e) {
                        let data = e.params.data;
                        storeCustomerDetails(data.id, data.text);
                    }).on('change', function() {
                        let customerId = $(this).val();
                        let customerText = $(this).find('option:selected').text();
                        storeCustomerDetails(customerId, customerText);
                    });
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        for (let field in errors) {
                            toastr.error(errors[field][0]);
                        }
                    }else if(xhr.status == 409){
                        toastr.warning(xhr.responseJSON.message);
                    } else {
                        toastr.error('An error occurred while adding the customer');
                    }
                },
                complete: function() {
                    button.prop('disabled', false);
                    button.html(button.data('original-text'));
                }
            });
        });


        $(document).on('change', '#discount_input_type', function() {
            let discountInput = $('#discount_input');
            let discountInputType = $(this);
            let maxLimit = (discountInputType.val() === 'percent') ? 100 : 1000000000;
            discountInput.attr('max', maxLimit);
        });

        document.addEventListener('DOMContentLoaded', function() {
            let selectElement = document.querySelector('.discount-type');
            selectElement.addEventListener('change', function() {
                document.getElementById('discount_input').max = (this.value === 'percent' ? 100 :
                    1000000000);
            });
        });


        // document.addEventListener("DOMContentLoaded", () => {
        //     const posDiv = document.getElementById("pos-div");
        //     const fullscreenBtn = document.getElementById("fullscreen-btn");

        //     function toggleFullscreen() {
        //         if (!document.fullscreenElement) {
        //             posDiv.requestFullscreen().catch(err => {
        //                 console.error(`Error attempting to enable fullscreen mode: ${err.message}`);
        //             });
        //         } else {
        //             document.exitFullscreen();
        //         }
        //     }

        //     function exitOnEsc(event) {
        //         if (event.key === "Escape" && document.fullscreenElement) {
        //             document.exitFullscreen();
        //         }
        //     }

        //     fullscreenBtn.addEventListener("click", toggleFullscreen);
        //     document.addEventListener("keydown", exitOnEsc);
        // });


        document.querySelector('.category-scroll-container').addEventListener('wheel', function(e) {
            e.preventDefault();
            this.scrollLeft += e.deltaY;
        });

        $(document).ready(function(e) {
            // Track clicked submit button per form
            $(document).on('click', 'form button[type="submit"]', function () {
                const $form = $(this).closest('form');
                $form.find('button[type="submit"]').removeClass('clicked');
                $(this).addClass('clicked');
            });

            $(document).on('submit', 'form', function (e) {
                const $form = $(this);
                // Per-form lock (instead of global lock)
                
                if ($('#order_draft').val() !== 'draft') {
                    $("input[name='select_payment_type']").prop('required', true);

                    if (!this.checkValidity()) {
                        e.preventDefault();
                        this.reportValidity(); // shows browser message immediately
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

            function fetchData(categoryId = '', subcategoryId = '', keyword = '', partner = '') {
                $.ajax({
                    url: `/restaurant-panel/pos/new/${partner}`,
                    type: "GET",
                    data: {
                        category_id: categoryId,
                        subcategory_id: subcategoryId,
                        keyword: keyword,
                    },
                    beforeSend: function() {
                        // $('#loading').show();
                    },
                    success: function(response) {
                        $('.subcategory-list').html(response.subcategoryHtml);
                        $('#product-list').html(response.productHtml);
                    },
                    complete: function() {
                        // $('#loading').hide();
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                    }
                });
            }   

            $(document).on('click', '.category-item', function(e) {
                e.preventDefault();

                const categoryId = $(this).data('category');
                $('.category-item').removeClass('selected');
                $(this).addClass('selected');

                fetchData(categoryId, '', $('#search-keyword').val(), "{{ $orderPartner }}");
            });

            $(document).on('click', '.subcategory-item', function(e) {
                e.preventDefault();

                const subcategoryId = $(this).data('subcategory');
                $('.subcategory-item').removeClass('selected');
                $(this).addClass('selected');

                const categoryId = 17 || '';
                fetchData(categoryId, subcategoryId, $('#search-keyword').val(), "{{ $orderPartner }}");
            });

            $(document).on('keypress', '#search-keyword', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    const keyword = $(this).val();
                    const categoryId = 17 || '';
                    const subcategoryId = $('.subcategory-item.selected').data('subcategory') || '';

                    fetchData(categoryId, subcategoryId, keyword, "{{ $orderPartner }}");
                }
            });


            function formatCurrency(amount) {
                return `{{ Helpers::currency_symbol() }} ${amount.toFixed(3)}`;
            }

            function updateCalculations() {
                const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                const cashPaid = parseFloat($('#cash_paid').val()) || 0;
                const cardPaid = parseFloat($('#card_paid').val()) || 0;
                const totalPaid = cashPaid + cardPaid;
                const cashReturn = Math.max(totalPaid - invoiceAmount, 0);

                $('#cash_paid_display').text(formatCurrency(cashPaid));
                $('#cash_return').text(formatCurrency(cashReturn));
                const bankAccountSelect = $('#bank_account');

                if (cardPaid > invoiceAmount) {
                    alert('{{ translate('Card amount cannot be greater than the invoice amount.') }}');
                    $('#card_paid').val('');
                    bankAccountSelect.prop('required', false).prop('disabled', true);
                    return;
                }
                
                if (cardPaid > 0) {
                    bankAccountSelect.prop('required', true).prop('disabled', false);
                } else {
                    bankAccountSelect.prop('required', false).prop('disabled', true);
                }
            }

            function attachEventListeners() {
                $('#cash_paid, #card_paid').off('input').on('input', function() {
                    // updateCalculations();
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

            $('#orderFinalModal').on('shown.bs.modal', function() {
                updateCalculations();
                attachEventListeners();
            });

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
                updateCalculations();
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

            $(document).on('focus', '#orderFinalModal input, #cash_paid, #card_paid', function() {
                activeInput = $(this);
            });

            $(document).on('click', '.keypad-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const value = $(this).data('value');
                if (activeInput && activeInput.length) {
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

            $(document).on('click', '.keypad-clear', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (activeInput && activeInput.length) {
                    activeInput.val('');
                    activeInput.trigger('input');
                }
            });

            $(document).on('blur', '#cash_paid, #card_paid', function() {
                const currentVal = this.value;

                if (!isValidNumber(currentVal)) {
                    alert('Please enter a valid number');
                    this.value = ''; // Clear the input if it's invalid
                    $(this).trigger('input');
                }

                if (currentVal.endsWith('.')) {
                    this.value = currentVal.slice(0, -1);
                    $(this).trigger('input');
                }
            });

            const isValidNumber = (value) => {
                return !isNaN(value);
            };
        });

        // Printers working

        // const billPrinterName = @json($billPrinter);
        // const kitchenPrinterName = @json($kitchenPrinter);

        // document.addEventListener("DOMContentLoaded", function() {

        //     qz.security.setCertificatePromise(function(resolve, reject) {
        //         fetch('/qz/cert')
        //             .then(res => res.text())
        //             .then(resolve)
        //             .catch(reject);
        //     });

        //     qz.security.setSignatureAlgorithm("SHA512");

        //     qz.security.setSignaturePromise(function(toSign) {
        //         return function(resolve, reject) {
        //             fetch("/qz/sign", {
        //                     method: "POST",
        //                     headers: {
        //                         "Content-Type": "application/json",
        //                         "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
        //                             .getAttribute('content')
        //                     },
        //                     body: JSON.stringify({
        //                         data: toSign
        //                     })
        //                 })
        //                 .then(res => {
        //                     return res.json();
        //                 })
        //                 .then(data => {
        //                     if (data.signature) {
        //                         resolve(data.signature);
        //                     } else {
        //                         console.error("❌ No signature in response");
        //                         reject("Invalid signature response");
        //                     }
        //                 })
        //                 .catch(err => {
        //                     console.error("❌ Signature fetch error:", err);
        //                     reject(err);
        //                 });
        //         };
        //     });


        //     if (!qz.websocket.isActive()) {
        //         qz.websocket.connect().then(() => {
        //             initializePrinters();
        //         }).catch(err => {
        //             console.log("QZ Tray connection failed: " + err);
        //         });
        //     } else {
        //         initializePrinters();
        //     }
        // });

        // function initializePrinters() {
        //     let printersFound = 0;

        //     qz.printers.find(billPrinterName).then(function(printer) {
        //         const config = qz.configs.create(printer);
        //         const printableWrapper = document.getElementById('bill-print-content');
        //         if (!printableWrapper) {
        //             return;
        //         }
        //         const printableDiv = printableWrapper.querySelector('#printableArea');
        //         if (!printableDiv) {
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
        //     }).catch(err => {
        //         alert("Bill print failed: " + err);
        //     });

        //     qz.printers.find(kitchenPrinterName).then(function(printer) {
        //         const config = qz.configs.create(printer);
        //         const printableWrapper = document.getElementById('kitchen-print-content');
        //         if (!printableWrapper) {
        //             return;
        //         }
        //         const printableDiv = printableWrapper.querySelector('#printableArea');
        //         if (!printableDiv) {
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
        //     }).catch(err => {
        //         alert("Kitchen print failed: " + err);
        //     });
        // }

        
    </script>
@endpush
