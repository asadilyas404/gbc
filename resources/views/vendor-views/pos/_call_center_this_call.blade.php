@php
    $zoneOptions = $callCenterZones->map(function ($zone) {
        return [
            'id' => $zone->id,
            'restaurant_id' => $zone->restaurant_id,
            'name' => $zone->name,
            'minutes' => (int) $zone->delivery_minutes,
        ];
    })->values();
@endphp

@if (!$callCenterInstalled)
    <div class="alert alert-warning m-2">
        Call center tables are not installed yet. Run <code>php artisan call-center:install</code>, then reload.
    </div>
@else
    @php($callOpen = old('customer_phone') || old('customer_name') || old('address') || old('restaurant_id'))
    <div class="modal fade" id="this-call-modal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
            <div class="modal-content d-flex flex-column" style="max-height: calc(100vh - 2rem); overflow: hidden;">
                <div class="modal-header bg-light py-3">
                    <h4 class="modal-title">This call</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
    <form action="{{ route('vendor.call-center.store') }}" method="post" id="request-form" class="d-flex flex-column" style="flex: 1 1 auto; min-height: 0; overflow: hidden;">
        @csrf
        <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto;">
            <div class="form-group">
                <label for="cc_customer_phone">{{ translate('phone') }}</label>
                <input type="text" name="customer_phone" id="cc_customer_phone" class="form-control" maxlength="30" required
                    value="{{ old('customer_phone') }}" placeholder="Filled from the customer search" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="cc_customer_name">{{ translate('Customer Name') }}</label>
                <input type="text" name="customer_name" id="cc_customer_name" class="form-control" maxlength="191" required value="{{ old('customer_name') }}">
            </div>
            <div class="form-group">
                <label>Order type</label>
                <div class="row">
                    <div class="col-md-4 mb-1">
                        <label for="cc_delivery_type_take_away" class="form-group bg-light d-flex align-items-center gap-2 m-0 delivery_type-selection-box payment-selection-box">
                            <input type="radio" id="cc_delivery_type_take_away" name="delivery_type" value="take_away" required {{ old('delivery_type') === 'take_away' ? 'checked' : '' }}>
                            <span class="input-label m-0">{{ translate('Take Away') }} <br /> {{ translate('سفري') }}</span>
                        </label>
                    </div>
                    <div class="col-md-4 mb-1">
                        <label for="cc_delivery_type_dine_in" class="form-group bg-light d-flex align-items-center gap-2 m-0 delivery_type-selection-box payment-selection-box">
                            <input type="radio" id="cc_delivery_type_dine_in" name="delivery_type" value="dine_in" {{ old('delivery_type') === 'dine_in' ? 'checked' : '' }}>
                            <span class="input-label m-0">{{ translate('Dine In') }} <br /> {{ translate('تناول في المطعم') }}</span>
                        </label>
                    </div>
                    <div class="col-md-4 mb-1">
                        <label for="cc_delivery_type_delivery" class="form-group bg-light d-flex align-items-center gap-2 m-0 delivery_type-selection-box payment-selection-box">
                            <input type="radio" id="cc_delivery_type_delivery" name="delivery_type" value="delivery" {{ old('delivery_type') === 'delivery' ? 'checked' : '' }}>
                            <span class="input-label m-0">{{ translate('Delivery') }} <br /> {{ translate('توصيل') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="cc_address">{{ translate('messages.address') }}</label>
                @if ($callCenterMapKey)
                    <small class="text-muted d-block mb-1">Drop a pin on the map to fill this in.</small>
                @endif
                <textarea name="address" id="cc_address" class="form-control" rows="2" maxlength="500" required>{{ old('address') }}</textarea>
            </div>

            @if ($callCenterMapKey)
                <div class="form-group">
                    <label for="pac-input">Location</label>
                    <div id="call-center-map" style="height: 180px;" class="rounded border"></div>
                    <input id="pac-input" class="form-control mt-1" type="text" placeholder="{{ translate('messages.search_here') }}">
                </div>
            @endif
            <input type="hidden" name="latitude" id="cc_latitude" value="{{ old('latitude') }}">
            <input type="hidden" name="longitude" id="cc_longitude" value="{{ old('longitude') }}">

            <div class="form-group">
                <label for="restaurant_id">Branch</label>
                <select name="restaurant_id" id="restaurant_id" class="form-control" required>
                    <option value="">Select branch</option>
                    @foreach ($callCenterBranches as $branch)
                        <option value="{{ $branch->branch_id }}" {{ (string) old('restaurant_id') === (string) $branch->branch_id ? 'selected' : '' }}>
                            {{ $branch->branch_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="zone_id">Zone</label>
                <select name="zone_id" id="zone_id" class="form-control" required data-selected="{{ old('zone_id') }}">
                    <option value="">Select a branch first</option>
                </select>
            </div>
            <div class="alert alert-soft-info" id="customer-eta">Choose a zone to see the time.</div>
            <div class="form-group mb-2">
                <label for="cc_delivery_fee">{{ translate('messages.delivery_fee') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                <input type="number" name="delivery_fee" id="cc_delivery_fee" class="form-control" min="0" max="999" step="0.001" required value="{{ old('delivery_fee', number_format(0, (int) (config('round_up_to_digit') ?: 3), '.', '')) }}">
            </div>
        </div>
        <div class="modal-footer flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" id="clear-customer">Clear</button>
            <button type="submit" class="btn btn--primary" id="save-request">Save request</button>
        </div>
    </form>
            </div>
        </div>
    </div>
@endif

@push('script_2')
@if ($callCenterInstalled)
<script>
    (function () {
        const ZONES = @json($zoneOptions);
        const PREP = {{ (int) $callCenterPreparationMinutes }};
        const HANDOVER = {{ (int) $callCenterHandoverMinutes }};
        const WAITING_URL = @json(route('vendor.call-center.waiting'));

        function fillZones(select, branchId, selected) {
            if (!select) return;
            if (!branchId) {
                select.innerHTML = '<option value="">Select a branch first</option>';
                return;
            }
            const zones = ZONES.filter(function (zone) {
                return String(zone.restaurant_id) === String(branchId);
            });
            select.innerHTML = '';
            if (!zones.length) {
                select.innerHTML = '<option value="">No zones for this branch</option>';
                return;
            }
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select zone';
            select.appendChild(placeholder);
            zones.forEach(function (zone) {
                const option = document.createElement('option');
                option.value = zone.id;
                option.textContent = zone.name + ' (' + zone.minutes + ' min drive)';
                option.dataset.minutes = zone.minutes;
                if (String(selected) === String(zone.id)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        function showEta(select, target) {
            if (!select || !target) return null;
            const minutes = select.options[select.selectedIndex] ? select.options[select.selectedIndex].dataset.minutes : '';
            if (minutes === undefined || minutes === '') {
                target.textContent = 'Choose a zone to see the time.';
                return null;
            }
            const told = PREP + Number(minutes) + HANDOVER;
            target.textContent = 'Tell the customer: ' + told + ' minutes.';
            return told;
        }

        function saveDraft() {
            const draft = {};
            ['cc_customer_name', 'cc_customer_phone', 'cc_address', 'restaurant_id', 'zone_id', 'cc_delivery_fee', 'cc_latitude', 'cc_longitude'].forEach(function (id) {
                const field = document.getElementById(id);
                if (field) draft[id] = field.value;
            });
            const orderType = document.querySelector('#request-form input[name="delivery_type"]:checked');
            draft.delivery_type = orderType ? orderType.value : '';
            sessionStorage.setItem('call-center-draft', JSON.stringify(draft));
        }

        function clearCustomer() {
            ['cc_customer_name', 'cc_customer_phone', 'cc_address', 'cc_latitude', 'cc_longitude'].forEach(function (id) {
                const field = document.getElementById(id);
                if (field) field.value = '';
            });
            const mapSearch = document.getElementById('pac-input');
            if (mapSearch) mapSearch.value = '';
            updateCallSummary();
            if (window.callCenterMarker) {
                window.callCenterMarker.setVisible(false);
            }
            if ($('#customer').length) {
                $('#customer').val(null).trigger('change');
                localStorage.removeItem('posSelectedCustomer');
            }
            saveDraft();
        }

        function readDraft() {
            @if (session('call_center_saved'))
                sessionStorage.removeItem('call-center-draft');
                return {};
            @endif
            try {
                return JSON.parse(sessionStorage.getItem('call-center-draft') || '{}');
            } catch (error) {
                return {};
            }
        }

        function restoreDraft(draft) {
            if (draft.delivery_type) {
                const radio = document.querySelector('#request-form input[name="delivery_type"][value="' + draft.delivery_type + '"]');
                if (radio) radio.checked = true;
            }
            Object.keys(draft).forEach(function (id) {
                if (id === 'zone_id' || id === 'delivery_type') return;
                const field = document.getElementById(id);
                if (field && !field.value && draft[id]) {
                    field.value = draft[id];
                }
            });
        }

        function prepareMoves(root) {
            root.querySelectorAll('.cc-move').forEach(function (form) {
                const branch = form.querySelector('.cc-move-branch');
                const zone = form.querySelector('.cc-move-zone');
                fillZones(zone, branch.value, zone.getAttribute('data-selected'));
                showEta(zone, form.querySelector('.cc-move-eta'));
            });
        }

        function updateCallSummary() {
            const phone = ($('#cc_customer_phone').val() || '').trim();
            const name = ($('#cc_customer_name').val() || '').trim();
            const branch = $('#restaurant_id').val() ? $('#restaurant_id option:selected').text().trim() : '';
            const zone = $('#zone_id').val() ? $('#zone_id option:selected').text().trim() : '';
            const parts = [];
            if (name) parts.push(name);
            if (phone) parts.push(phone);
            if (branch) parts.push(branch);
            if (zone) parts.push(zone);
            $('#this-call-summary').text(parts.length ? parts.join(' · ') : 'Add phone, address, and branch');
        }

        window.fillCallFromPosCustomer = function (customer) {
            if (!customer) return;
            if (customer.phone) $('#cc_customer_phone').val(customer.phone);
            if (customer.name) $('#cc_customer_name').val(customer.name);
            if (customer.address) $('#cc_address').val(customer.address);
            if (customer.latitude && customer.longitude && typeof window.setCallCenterPin === 'function') {
                window.setCallCenterPin(parseFloat(customer.latitude), parseFloat(customer.longitude), customer.address || '');
            }
            updateCallSummary();
            saveDraft();
        };

        function resizeCallMap() {
            if (!window.callCenterMap || typeof google === 'undefined') return;
            google.maps.event.trigger(window.callCenterMap, 'resize');
            const lat = parseFloat($('#cc_latitude').val());
            const lng = parseFloat($('#cc_longitude').val());
            if (!isNaN(lat) && !isNaN(lng)) {
                window.callCenterMap.setCenter({ lat: lat, lng: lng });
            }
        }

        const draft = readDraft();
        restoreDraft(draft);
        fillZones(document.getElementById('zone_id'), $('#restaurant_id').val(), $('#zone_id').data('selected') || draft.zone_id || '');
        showEta(document.getElementById('zone_id'), document.getElementById('customer-eta'));
        updateCallSummary();

        @if ($callOpen)
        $('#this-call-modal').modal('show');
        @endif

        $('#restaurant_id').on('change', function () {
            fillZones(document.getElementById('zone_id'), this.value, '');
            showEta(document.getElementById('zone_id'), document.getElementById('customer-eta'));
            saveDraft();
        });
        $('#zone_id').on('change', function () {
            showEta(this, document.getElementById('customer-eta'));
            saveDraft();
        });
        $('#request-form').on('keydown', 'input', function (event) {
            if (event.key === 'Enter') event.preventDefault();
        });
        $('#clear-customer').on('click', clearCustomer);
        $('#request-form').on('input change', 'input, textarea, select', function () {
            saveDraft();
            updateCallSummary();
        });

        $('#request-form').on('submit', function (event) {
            const hasItems = $('#cart .product-title1, #cart .media').length > 0 || $('#cart table tbody tr').length > 0;
            const emptyMsg = $('#cart .search--no-found, #cart .text-center.text-muted').length > 0;
            if (!hasItems || emptyMsg) {
                // Fall back to checking cart rows more loosely via proceed buttons disabled state
            }
            const cartHasProduct = $('#cart').find('[data-key], .remove-From-Cart, .update-Quantity').length > 0;
            if (!cartHasProduct) {
                event.preventDefault();
                toastr.error('Add at least one item.');
            }
        });

        let requestStatus = 'pending';
        let requestQuery = '';
        let requestTimer = null;

        function loadRequests(options) {
            options = options || {};
            if (options.status) requestStatus = options.status;
            if (options.q !== undefined) requestQuery = options.q;
            const page = options.page || 1;
            $('#requests-body').css('opacity', '0.6');
            return $.get(WAITING_URL, { status: requestStatus, q: requestQuery, page: page }).done(function (data) {
                $('#requests-body').html(data.html);
                $('#requests-count').text(data.total);
                prepareMoves(document.getElementById('requests-body'));
                if (options.focus) {
                    const input = document.getElementById('cc-request-search');
                    if (input) {
                        input.focus();
                        const end = input.value.length;
                        input.setSelectionRange(end, end);
                    }
                }
            }).fail(function () {
                $('#requests-body').html('<p class="text-danger mb-0 p-3">The requests could not be loaded.</p>');
            }).always(function () {
                $('#requests-body').css('opacity', '1');
            });
        }

        $(document).on('click', '.cc-request-tab', function (event) {
            event.preventDefault();
            loadRequests({ status: String($(this).data('status')), page: 1, focus: true });
        });
        $(document).on('input', '#cc-request-search', function () {
            const value = this.value;
            clearTimeout(requestTimer);
            requestTimer = setTimeout(function () {
                loadRequests({ q: value, page: 1, focus: true });
            }, 250);
        });
        $(document).on('click', '.cc-request-page', function () {
            if (this.disabled) return;
            loadRequests({ page: Number($(this).data('page')) || 1 });
        });
        $(document).on('click', '.cc-request-row', function () {
            const id = $(this).data('id');
            $('#cc-request-browser').addClass('d-none');
            $('.cc-request-detail').addClass('d-none');
            $('.cc-request-detail[data-id="' + id + '"]').removeClass('d-none');
        });
        $(document).on('click', '.cc-request-back', function () {
            $('.cc-request-detail').addClass('d-none');
            $('#cc-request-browser').removeClass('d-none');
        });

        $('#open-requests').on('click', function () {
            requestStatus = 'pending';
            requestQuery = '';
            $('#requests-body').html('<p class="text-muted mb-0 p-3">Loading requests…</p>');
            $('#requests-modal').modal('show');
            loadRequests({ status: 'pending', q: '', page: 1 });
        });

        $(document).on('change', '.cc-move-branch', function () {
            const form = this.form;
            fillZones(form.querySelector('.cc-move-zone'), this.value, '');
            showEta(form.querySelector('.cc-move-zone'), form.querySelector('.cc-move-eta'));
        });
        $(document).on('change', '.cc-move-zone', function () {
            showEta(this, this.form.querySelector('.cc-move-eta'));
        });
        $(document).on('submit', '.cc-move', function (event) {
            const zone = this.querySelector('.cc-move-zone');
            const told = showEta(zone, this.querySelector('.cc-move-eta'));
            if (!told && told !== 0) {
                event.preventDefault();
                toastr.error('Choose a zone for that branch.');
                return;
            }
            if (!confirm('Send this request to the selected branch? Tell the customer ' + told + ' minutes.')) {
                event.preventDefault();
            }
        });
        $(document).on('submit', '.cc-cancel', function (event) {
            if (!confirm('Cancel this request? The branch will not receive it.')) {
                event.preventDefault();
            }
        });

        @if ($callCenterMapKey)
        window.initCallCenterMap = function () {
            const start = { lat: {{ (float) $callCenterMapLat }}, lng: {{ (float) $callCenterMapLng }} };
            const savedLat = parseFloat($('#cc_latitude').val());
            const savedLng = parseFloat($('#cc_longitude').val());
            const center = !isNaN(savedLat) && !isNaN(savedLng) ? { lat: savedLat, lng: savedLng } : start;
            const map = new google.maps.Map(document.getElementById('call-center-map'), { zoom: 13, center: center });
            const marker = new google.maps.Marker({ map: map, position: !isNaN(savedLat) ? center : null });
            window.callCenterMap = map;
            window.callCenterMarker = marker;
            const input = document.getElementById('pac-input');
            const searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);
            map.addListener('bounds_changed', function () {
                searchBox.setBounds(map.getBounds());
            });
            searchBox.addListener('places_changed', function () {
                const places = searchBox.getPlaces();
                if (!places || !places.length || !places[0].geometry) return;
                const place = places[0];
                marker.setVisible(true);
                marker.setPosition(place.geometry.location);
                map.setCenter(place.geometry.location);
                map.setZoom(15);
                window.setCallCenterPin(place.geometry.location.lat(), place.geometry.location.lng(), place.formatted_address || input.value);
            });
            map.addListener('click', function (event) {
                marker.setVisible(true);
                marker.setPosition(event.latLng);
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: event.latLng }, function (results, status) {
                    const address = status === 'OK' && results[0] ? results[0].formatted_address : '';
                    window.setCallCenterPin(event.latLng.lat(), event.latLng.lng(), address);
                });
            });
        };
        window.setCallCenterPin = function (lat, lng, address) {
            $('#cc_latitude').val(lat);
            $('#cc_longitude').val(lng);
            if (address) $('#cc_address').val(address);
            if (window.callCenterMap && window.callCenterMarker && !isNaN(lat) && !isNaN(lng)) {
                const position = { lat: Number(lat), lng: Number(lng) };
                window.callCenterMarker.setVisible(true);
                window.callCenterMarker.setPosition(position);
                window.callCenterMap.setCenter(position);
                window.callCenterMap.setZoom(15);
            }
            saveDraft();
        };

        function ensureCallCenterMap() {
            const box = document.getElementById('call-center-map');
            if (!box || typeof google === 'undefined' || !google.maps) return false;
            if (!window.callCenterMap) {
                window.initCallCenterMap();
            }
            resizeCallMap();
            setTimeout(resizeCallMap, 300);
            return true;
        }

        $('#this-call-modal').on('shown.bs.modal', function () {
            if (ensureCallCenterMap()) return;
            const wait = setInterval(function () {
                if (ensureCallCenterMap()) clearInterval(wait);
            }, 200);
            setTimeout(function () { clearInterval(wait); }, 10000);
        });
        @endif
    })();
</script>
@endif
@endpush
