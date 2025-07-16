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

        .category-item.selected {
            padding: 5px;
            border-radius: 10px;
            background-color: #F8923B;
            color: #fff;
            box-shadow: 0 4px 10px rgba(64, 169, 255, 0.5);
        }

        .category-item.selected:hover {
            color: #fff;
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
    </style>

    <div id="pos-div" class="content container-fluid" style="background-color: white; padding-top: 0;">
        @php($restaurant_data = Helpers::get_restaurant_data())
        <div class="d-flex flex-wrap">
            <div class="order--pos-left">
                <!-- Subcategories (Vertical Scroll Attached to Card) -->

                {{-- @if ($subcategories->isNotEmpty()) --}}
                <style>
                    /* Subcategory Scroll Styles */
                    .main-content {
                        margin-left: 80px;
                    }

                    [dir="rtl"] .main-content {
                        margin-left: 0;
                        margin-right: 80px;
                    }

                    .subcategory-scroll-container {
                        position: fixed;
                        top: 5;
                        left: 0;
                        height: 100vh;
                        width: 80px;
                        border-radius: 5px;
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
                            {{-- <div class="col-sm-6">
                                <div class="input-group">
                                    <select name="category" id="category"
                                            class="form-control js-select2-custom set-filter"
                                            data-url="{{ url()->full() }}" data-filter="category_id"
                                            title="{{ translate('messages.select_category') }}">
                                        <option value="">{{ translate('messages.all_categories') }}</option>
                                        @foreach ($categories as $item)
                                            <option
                                                value="{{ $item->id }}" {{ $category == $item->id ? 'selected' : '' }}>
                                                {{ Str::limit($item->name, 20, '...') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div> --}}

                            <div class="col-sm-12">
                                <div class="category-scroll-container">
                                    <div class="category-scroll">
                                        <a href="javascript:void(0);"
                                            class="category-item {{ empty($category) ? 'selected' : '' }}" data-category="">
                                            <div class="category-icon">
                                                <img src="{{ dynamicAsset('/public/assets/admin/img/100x100/food.png') }}"
                                                    alt="All Products">
                                            </div>
                                            <div class="category-name">
                                                {{ translate('messages.all_menu') }}
                                            </div>
                                        </a>
                                        @foreach ($categories as $item)
                                            <a href="javascript:void(0);"
                                                class="category-item {{ $category == $item->id ? 'selected' : '' }}"
                                                data-category="{{ $item->id }}">
                                                <div class="category-icon">
                                                    <img src="{{ $item['image_full_url'] }}" alt="{{ $item->name }}">
                                                </div>
                                                <div class="category-name">
                                                    {{ Str::limit($item->name, 20, '...') }}
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
                <div class="card">
                    <div class="card-header bg-light border-0 m-1 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <span>
                                {{ translate('Billing Section') }}
                            </span>
                        </h5>
                        <a class="btn btn--primary" href="{{ route('vendor.order.list', ['draft']) }}"
                            title="{{ translate('messages.Unpaid Orders') }}">
                            {{ translate('messages.Unpaid Orders') }}
                        </a>
                    </div>
                    <div class="w-100">
                        <div class="d-flex flex-wrap flex-row p-2 add--customer-btn">
                            <label for='customer'></label>
                            <select id='customer' name="customer_id"
                                data-placeholder="{{ translate('messages.walk_in_customer') }}"
                                class="js-data-example-ajax form-control"></select>
                            <button class="btn btn--primary" data-toggle="modal"
                                data-target="#add-customer">{{ translate('Add New Customer') }}</button>
                        </div>
                        @if (
                            ($restaurant_data->restaurant_model == 'commission' && $restaurant_data->self_delivery_system == 1) ||
                                ($restaurant_data->restaurant_model == 'subscription' &&
                                    isset($restaurant_data->restaurant_sub) &&
                                    $restaurant_data->restaurant_sub->self_delivery == 1))
                            <div class="pos--delivery-options">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title">
                                        <span class="card-title-icon">
                                            <i class="tio-user"></i>
                                        </span>
                                        <span>{{ translate('Delivery_Information') }}</span>
                                    </h5>
                                    <span class="delivery--edit-icon text-primary" id="delivery_address" data-toggle="modal"
                                        data-target="#paymentModal"><i class="tio-edit"></i></span>
                                </div>
                                <div class="pos--delivery-options-info d-flex flex-wrap" id="del-add">
                                    @include('vendor-views.pos._address')
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class='w-100' id="cart">
                        @include('vendor-views.pos._cart')
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="quick-view" tabindex="-1">
            <div class="modal-dialog">
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

            <div class="modal fade" id="print-invoice" tabindex="-1">
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
            </div>
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
                                        <label for="f_name" class="input-label">{{ translate('first_name') }} <span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="f_name" type="text" name="f_name" class="form-control"
                                            value="{{ old('f_name') }}" placeholder="{{ translate('first_name') }}"
                                            required>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="l_name" class="input-label">{{ translate('last_name') }} <span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="l_name" type="text" name="l_name" class="form-control"
                                            value="{{ old('l_name') }}" placeholder="{{ translate('last_name') }}"
                                            required>
                                    </div>
                                </div>
                            </div>
                            <div class="row pl-2">
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="email" class="input-label">{{ translate('email') }}<span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="email" type="email" name="email" class="form-control"
                                            value="{{ old('email') }}"
                                            placeholder="{{ translate('Ex_:_ex@example.com') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="phone" class="input-label">{{ translate('phone') }}
                                            ({{ translate('with_country_code') }})<span
                                                class="input-label-secondary text-danger">*</span></label>
                                        <input id="phone" type="tel" name="phone" class="form-control"
                                            value="{{ old('phone') }}" placeholder="{{ translate('phone') }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{ translate('reset') }}</button>
                                <button type="submit" id="submit_new_customer"
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
        src="https://maps.googleapis.com/maps/api/js?key={{ BusinessSetting::where('key', 'map_api_key')->first()->value }}&libraries=places&callback=initMap&v=3.49">
    </script>
    <script src="{{ dynamicAsset('public/assets/admin/js/view-pages/pos.js') }}"></script>
    {{-- <script src="{{ dynamicAsset('public/assets/restaurant_panel/qz-tray.js') }}"></script> --}}
    <script src="https://cdn.jsdelivr.net/npm/rsvp@4.8.5/dist/rsvp.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/js-sha256@0.9.0/build/sha256.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.1.0/qz-tray.js"></script>

    <script>
        "use strict";

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


        $('#search-form').on('submit', function(e) {
            e.preventDefault();
            let keyword = $('#datatableSearch').val();
            let nurl = new URL('{!! url()->full() !!}');
            nurl.searchParams.set('keyword', keyword);
            location.href = nurl;
        });


        $(document).on('click', '.quick-View', function() {
            $.get({
                url: '{{ route('vendor.pos.quick-view') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id')
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    console.log("success...")
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });

        $(document).on('click', '.quick-View-Cart-Item', function() {
            $.get({
                url: '{{ route('vendor.pos.quick-view-cart-item') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('product-id'),
                    item_key: $(this).data('item-key'),
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    console.log("success...")
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
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

        function getVariantPrice() {
            getCheckedInputs();

            // Get discount values from the input fields
            var discountAmount = $('#product_discount').val() || 0;
            var discountType = $('#product_discount_type').val();

            // Ensure the quantity is greater than zero
            if ($('#add-to-cart-form input[name=quantity]').val() > 0) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });

                $.ajax({
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
                            $('#quantity_increase_button').removeAttr("disabled");

                        }
                    },
                    error: function() {
                        toastr.error('Something went wrong. Please try again.');
                    }
                });
            }
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
            $.post({
                url: '{{ route('vendor.pos.add-to-cart') }}',
                data: $('#' + form_id).serializeArray(),
                beforeSend: function() {
                    $('#loading').show();
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
                        Swal.fire({
                            icon: 'info',
                            title: 'Cart',
                            text: "{{ translate('messages.product_has_been_updated_in_cart') }}"
                        });

                        return false;
                    } else if (data.data === 'stock_out') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cart',
                            text: data.message
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
                    $('#loading').hide();
                    isProcessing = false;
                    button.prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.remove-From-Cart', function() {
            let key = $(this).data('product-id');
            $.post('{{ route('vendor.pos.remove-from-cart') }}', {
                _token: '{{ csrf_token() }}',
                key: key
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

            });
        });

        $(document).on('click', '.empty-Cart', function() {
            $.post('{{ route('vendor.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }, function() {
                $('#del-add').empty();
                updateCart();
                toastr.info('{{ translate('messages.item_has_been_removed_from_cart') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            });
        });

        function updateCart() {
            $.post('<?php echo e(route('vendor.pos.cart_items')); ?>', {
                _token: '<?php echo e(csrf_token()); ?>'
            }, function(data) {
                $('#cart').empty().html(data);
            });
        }

        $(document).on('click', '.delivery-Address-Store', function() {
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
                    $('#loading').show();
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
                    $('#loading').hide();
                    $('#paymentModal').modal('hide');
                }
            });
        });

        $(document).on('click', '.payable-Amount', function() {
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
                    $('#loading').show();
                },
                success: function() {
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function() {
                    $('#loading').hide();
                    $('#insertPayableAmount').modal('hide');
                }
            });
        });

        $(document).on('change', '[name="quantity"]', function(event) {
            getVariantPrice();
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
            // Allow: backspace, delete, tab, escape, enter and .
            if (event.type === 'keydown') {
                if ($.inArray(event.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (event.keyCode === 65 && event.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (event.keyCode >= 35 && event.keyCode <= 39)) {
                    // let it happen, don't do anything
                    return;
                }
                // Ensure that it is a number and stop the keypress
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

        $(document).on('change', '#discount_input_type', function() {
            let discountInput = $('#discount_input');
            let discountInputType = $(this);
            let maxLimit = (discountInputType.val() === 'percent') ? 100 : 1000000000;
            discountInput.attr('max', maxLimit);
        });
        $("#customer").change(function() {
            if ($(this).val()) {
                $('#customer_id').val($(this).val());
            }
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

        $(document).on('click', '.quick-View', function() {
            $.get({
                url: '{{ route('vendor.pos.quick-view') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id')
                },
                beforeSend: function() {
                    $('#loading').show();
                },
                success: function(data) {
                    console.log("success...")
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function() {
                    $('#loading').hide();
                },
            });
        });

        $(document).ready(function() {

            function fetchData(categoryId = '', subcategoryId = '', keyword = '') {
                $.ajax({
                    url: "{{ url('restaurant-panel/pos/new') }}",
                    type: "GET",
                    data: {
                        category_id: categoryId,
                        subcategory_id: subcategoryId,
                        keyword: keyword
                    },
                    beforeSend: function() {
                        $('#loading').show();
                    },
                    success: function(response) {
                        // Update subcategories
                        $('.subcategory-list').html(response.subcategoryHtml);
                        // Update products
                        $('#product-list').html(response.productHtml);
                    },
                    complete: function() {
                        $('#loading').hide();
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                    }
                });
            }

            // Handle category click
            $(document).on('click', '.category-item', function(e) {
                e.preventDefault();

                const categoryId = $(this).data('category');
                $('.category-item').removeClass('selected');
                $(this).addClass('selected');

                fetchData(categoryId, '', $('#search-keyword').val());
            });

            // Handle subcategory click
            $(document).on('click', '.subcategory-item', function(e) {
                e.preventDefault();

                const subcategoryId = $(this).data('subcategory');
                $('.subcategory-item').removeClass('selected');
                $(this).addClass('selected');

                const categoryId = $('.category-item.selected').data('category') || '';
                // Fetch products for the selected subcategory
                fetchData(categoryId, subcategoryId, $('#search-keyword').val());
            });

            // Handle search on Enter key
            $(document).on('keypress', '#search-keyword', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    const keyword = $(this).val();
                    const categoryId = $('.category-item.selected').data('category') || '';
                    const subcategoryId = $('.subcategory-item.selected').data('subcategory') || '';

                    // Fetch products based on the search keyword
                    fetchData(categoryId, subcategoryId, keyword);
                }
            });

            //Order final Model Calculations

            function formatCurrency(amount) {
                return `{{ Helpers::currency_symbol() }} ${amount.toFixed(3)}`;
            }

            function updateCalculations() {
                const invoiceAmount = parseFloat($('#invoice_amount span').text()) || 0;
                console.log('amount ' + invoiceAmount);
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
                    bankAccountSelect.prop('required', false).prop('disabled', true).val('');
                    return;
                }

                // Enable/disable bank account selection
                if (cardPaid > 0) {
                    bankAccountSelect.prop('required', true).prop('disabled', false);
                } else {
                    bankAccountSelect.prop('required', false).prop('disabled', true).val('');
                }

            }

            function attachEventListeners() {
                $('#cash_paid, #card_paid').off('input').on('input', function() {
                    updateCalculations();
                });
            }

            // Call updateCalculations when the modal is opened
            $('#orderFinalModal').on('shown.bs.modal', function() {
                updateCalculations(); // Recalculate on modal open
                attachEventListeners(); // Ensure input listeners are attached
            });

            // Trigger calculations if the modal inputs are dynamically added
            $(document).on('input', '#cash_paid, #card_paid', function() {
                updateCalculations();
            });


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

        });

        // Printers working

        const billPrinterName = @json($billPrinter);
        const kitchenPrinterName = @json($kitchenPrinter);

        console.log(billPrinterName);
        console.log(kitchenPrinterName);

        document.addEventListener("DOMContentLoaded", function() {

            qz.security.setCertificatePromise(function(resolve, reject) {
                resolve(`-----BEGIN CERTIFICATE-----
MIIC+zCCAeOgAwIBAgIJAM8MMz5wiJAvMA0GCSqGSIb3DQEBCwUAMBQxEjAQBgNV
BAMMCWxvY2FsaG9zdDAeFw0yNTA3MTAwNTUxMjVaFw0yNjA3MTAwNTUxMjVaMBQx
EjAQBgNVBAMMCWxvY2FsaG9zdDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC
ggEBAMIROhqMyvydDbdlerVAJpvf3ppaOnVzPP29FbhfBZKrO3zoZGJPnLoDWg5S
WGDqQaTjnArWbfp4nLYOzHdavYK4m1qgp5o4WlhLd5ADDXpdz2PLBBcbpJoUn/Kx
BYuhWFYDGgZzD7oxYg4Pmgqo12/1+G9coVG9vRKLgKO1jQqWnl0k6wXeXIkp6RAC
pAtvDyCh+MwG8u2nhnkQBp4fkhBrAvHjqywsbu/yR+dvbJm06iz3QJqS7tCoDboz
vEuMYbswKf4lzSLF1hqJkHLriD98aND56GBR1WMlmvN1z+JRA9bHRSAKsuKtAmp7
yaX4HB2mAMvcXAvTzW1nA3UuQVsCAwEAAaNQME4wHQYDVR0OBBYEFLIX+Q6flGiE
nWj0QZI5feShi9LMMB8GA1UdIwQYMBaAFLIX+Q6flGiEnWj0QZI5feShi9LMMAwG
A1UdEwQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBACrS1IzPsVHXRGg1wOLZ1dib
kMAc+ia2hb6n4Biuv9b+dOOEgWT3GaZ8VUv5igEMokMnvmSdwORy8vdmddSeV+Hd
cXcDJ7tFdIV2xsKAHNdMYC+YIoQ8jZuP4Fii1TvYxvI6hF45ZFsSyQaWBL+4ic6q
bwQAIE2603u1WyFe6XdUm2apzVVmbwk34OM58yNWgqmyWKojQqx4QTpA9gtGTkfG
9fvqRmLyZCdvflRzECA+qXKb2d+31kEUSanceUunZk0YTxAQpKVFXxjXdHvmqQ6U
9pWqnpi/kPkzzfgoUVLomc9YHPoiRwkX53kFYB79Rs/QS0NLhus4LYdnXQvwm1o=
-----END CERTIFICATE-----
`);
            });

            qz.security.setSignaturePromise(function(toSign) {
                return new Promise(function(resolve, reject) {
                    fetch("/qz/sign", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                data: toSign
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.signature) {
                                resolve(data.signature);
                            } else {
                                reject("Invalid signature response");
                            }
                        })
                        .catch(err => reject(err));
                });
            });


            if (!qz.websocket.isActive()) {
                qz.websocket.connect().then(() => {
                    initializePrinters();
                }).catch(err => {
                    alert("QZ Tray connection failed: " + err);
                });
            } else {
                initializePrinters();
            }
        });

        function initializePrinters() {
            let printersFound = 0;

            qz.printers.find(billPrinterName).then(function(printer) {
                const config = qz.configs.create(printer);
                const printableDiv = document.getElementById('bill-print-content');
                if (!printableDiv) {
                    return;
                }
                const html = printableDiv.innerHTML;
                console.log(html);
                if (!html) {
                    return;
                }
                const data = [{
                    type: 'html',
                    format: 'plain',
                    data: html
                }];

                return qz.print(config, data);
            }).then(() => {
                console.log("Bill print done");
            }).catch(err => {
                alert("Bill print failed: " + err);
            });

            qz.printers.find(kitchenPrinterName).then(function(printer) {
                const config = qz.configs.create(printer);
                const printableDiv = document.getElementById('kitchen-print-content');
                if (!printableDiv) {
                    return;
                }
                const html = printableDiv.innerHTML;

                const data = [{
                    type: 'html',
                    format: 'plain',
                    data: html
                }];

                return qz.print(config, data);
            }).then(() => {
                console.log("Kitchen print done");
            }).catch(err => {
                alert("Kitchen print failed: " + err);
            });
        }
    </script>
@endpush
