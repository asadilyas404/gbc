@php
    use App\CentralLogics\Helpers;
    use App\Models\AddOn;
    use App\Models\OptionsList;
    $index = 0;
@endphp
<div class="initial-49">
    <div class="modal-header p-0">
        <h4 class="modal-title product-title">
        </h4>
        <button class="close call-when-done" type="button" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
        </button>
    </div>

    <div class="modal-body">
        <div class="d-flex flex-row align-items-center">
            <div class="d-flex align-items-center justify-content-center active position-relative">
                @if (config('toggle_veg_non_veg'))
                    <span
                        class="badge badge-{{ $product->veg ? 'success' : 'danger' }} position-absolute left-0 top-0">{{ $product->veg ? translate('messages.veg') : translate('messages.non_veg') }}</span>
                @endif
                @if ($product->stock_type !== 'unlimited' && $product->item_stock <= 0)
                    <span
                        class="badge badge-danger position-absolute left-0 top-0">{{ translate('messages.Out_of_Stock') }}</span>
                @endif
                <img class="img-responsive mr-3 img--100 onerror-image"
                    src="{{ $product->image_full_url ?? dynamicAsset('public/assets/admin/img/100x100/food-default-image.png') }}"
                    data-onerror-image="{{ dynamicAsset('public/assets/admin/img/100x100/food-default-image.png') }}"
                    data-zoom="{{ $product->image_full_url }}" alt="Product image">
                <div class="cz-image-zoom-pane"></div>
            </div>

            <div class="details pl-2">
                <a href="{{ route('vendor.food.view', $product->id) }}"
                    class="h3 mb-2 product-title text-capitalize text-break">{{ $product->name }}</a>

                <div class="mb-3 text-dark">
                    <span class="h3 font-weight-normal text-accent mr-1" id="product-price">
                        {{ Helpers::get_price_range($product, true) }}
                    </span>
                    {{-- @if ($product->discount > 0 || Helpers::get_restaurant_discount($product->restaurant))
                        <span class="fz-12px line-through">
                            {{ Helpers::get_price_range($product) }}
                    </span>
                    @endif --}}
                    <span
                        class="fz-12px line-through {{ ($cart_item['discountAmount'] ?? 0) > 0 || Helpers::get_restaurant_discount($product->restaurant) ? '' : 'd-none' }}"
                        id="original-price">
                        {{ Helpers::get_price_range($product) }}
                    </span>
                </div>

                {{-- @if ($product->discount > 0 || Helpers::get_restaurant_discount($product->restaurant)) --}}
                <div class="mb-3 text-dark">
                    <strong>{{ translate('messages.discount') }} : </strong>
                    <strong id="set-discount-amount">{{ $cart_item['discountAmount'] ?? 0 }}</strong>
                </div>
                {{-- @endif --}}

            </div>
        </div>

        <div class="row pt-2">
            <div class="col-12">
                @if(!empty($product->description))
                    <h2>{{ translate('messages.description') }}</h2>
                    <span class="d-block text-dark text-break">
                        {!! $product->description !!}
                    </span>
                @endif
                <form id="add-to-cart-form" class="mb-2">
                    @csrf
                    <input type="hidden" name="id" value="{{ $product->id }}">
                    <input type="hidden" name="cart_item_key" value="{{ $item_key }}">
                    <input type="hidden" id="cart_item_total_price" value="{{ (($cart_item['price'] * $cart_item['quantity']) - ($cart_item['discount_on_food'] ?? 0)) + ($cart_item['total_add_on_price'] ?? 0) }}">
                    <input type="hidden" name="base_price" id="base_price" value="{{ $product->price }}">
                    <input type="hidden" name="options_changed" id="options_changed" value="0">
                    <input type="hidden" name="is_printed" id="is_printed" value="{{ $cart_item['is_printed'] ?? 0 }}">
                    @php($values = [])
                    @php($selected_variations = isset($cart_item) ? $cart_item['variations'] : [])
                    @php($names = [])
                    @foreach ($selected_variations as $key => $var)
                        @if (isset($var['values']))
                            @php($names[$key] = $var['name'])
                            @foreach ($var['values'] as $k => $item)
                                @php($values[$key] = $item)
                            @endforeach
                        @endif
                    @endforeach
                    
                    <div class="row justify-content-between mt-4">
                        <div class="product-description-label mt-2 text-dark h3 col-12">
                            {{ translate('messages.Discount') }}:
                        </div>
                        <div class="form-group col-md-6">
                            <input type="number" class="form-control" name="product_discount" min="0.0001"
                                onkeyup="calculateTotal()"
                                id="product_discount" value="{{ $cart_item['discountAmount'] ?? 0 }}"
                                max="{{ $product['discount_type'] == 'percent' ? 100 : 1000000000 }}" step="0.0001">
                        </div>
                        <div class="form-group col-md-6">
                            <select name="product_discount_type" class="form-control discount-type"
                                id="product_discount_type" onchange="calculateTotal()">
                                <option value="amount"
                                    {{ ($cart_item['discountType'] ?? '') == 'amount' ? 'selected' : '' }}>
                                    {{ translate('messages.amount') }}
                                    ({{ Helpers::currency_symbol() }})
                                </option>
                                <option value="percent"
                                    {{ ($cart_item['discountType'] ?? '') == 'percent' ? 'selected' : '' }}>
                                    {{ translate('messages.percent') }}
                                    (%)
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="@if ($orderPaymentStatus == 'paid') pe-none @endif">
                        @foreach (json_decode($product->variations) as $key => $choice)
                            @if (isset($choice->name) && isset($choice->values))
                                <div class="h3 p-0 pt-2">{{ translate('variation') . ' # ' . ++$index . ' ('. $choice->name .')' }} <small class="text-muted fs-12">
                                        ({{ $choice->required == 'on' ? translate('messages.Required') : translate('messages.optional') }}
                                        ) </small>
                                </div>
                                @if ($choice->min != 0 && $choice->max != 0)
                                    <small class="d-block mb-3">
                                        {{ translate('You_need_to_select_minimum_ ') }} {{ $choice->min }}
                                        {{ translate('to_maximum_ ') }} {{ $choice->max }} {{ translate('options') }}
                                    </small>
                                @endif
                                <input type="hidden" name="variations[{{ $key }}][min]"
                                    value="{{ $choice->min }}">
                                <input type="hidden" name="variations[{{ $key }}][max]"
                                    value="{{ $choice->max }}">
                                <input type="hidden" name="variations[{{ $key }}][required]"
                                    value="{{ $choice->required }}">
                                <input type="hidden" name="variations[{{ $key }}][name]"
                                    value="{{ $choice->name }}">

                                <div class="d-flex justify-content-left flex-wrap">
                                    @foreach ($choice->values as $k => $option)
                                        <?php
                                            $showOption = true;
                                            if (isset($option->options_list_id) && $option->options_list_id) {
                                                $optionsList = OptionsList::find($option->options_list_id);
                                                $showOption = $optionsList && $optionsList->status == 1;
                                            }
                                        ?>
                                        @if ($showOption)
                                            <div class="col-4 px-2">
                                                <input
                                                    class="btn-check input-element {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'stock_out' : '' }}"
                                                    type="{{ $choice->type == 'multi' ? 'checkbox' : 'radio' }}"
                                                    id="choice-option-{{ $key }}-{{ $k }}"
                                                    data-option_id="{{ data_get($option, 'option_id') }}"
                                                    data-price="{{ data_get($option, 'optionPrice') }}"
                                                    name="variations[{{ $key }}][values][label][]"
                                                    value="{{ $option->label }}"
                                                    {{ isset($values) && in_array($option->label, $values[$key]) ? 'checked' : '' }}
                                                    {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'disabled' : '' }}
                                                    autocomplete="off">

                                                <label
                                                    class="d-flex align-items-center text-left btn btn-sm check-label text-break {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'stock_out text-muted' : 'text-dark' }}"
                                                    for="choice-option-{{ $key }}-{{ $k }}">
                                                    @if (isset($option->options_list_id) && $option->options_list_id)
                                                        {{ Str::limit(OptionsList::find($option->options_list_id)->name ?? $option->label, 30, '...') }}
                                                    @else
                                                        {{ Str::limit($option->label, 30, '...') }}
                                                    @endif

                                                    <br>
                                                    {{ Helpers::format_currency(data_get($option, 'optionPrice')) }}
                                                    <span
                                                        class="input-label-secondary text--title text--warning {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? '' : 'd-none' }}"
                                                        title="{{ translate('Currently_you_need_to_manage_discount_with_the_Restaurant.') }}">
                                                        <i class="tio-info-outlined"></i>
                                                        <small>{{ translate('stock_out') }}</small>
                                                    </span>
                                                </label>

                                                @if ($choice->type == 'multi')
                                                    <label
                                                        class="input-group addon-quantity-input mx-1 shadow bg-white rounded px-1"
                                                        for="choice-option-{{ $key }}-{{ $k }}">
                                                        <button class="btn btn-sm h-100 text-dark px-0 decrease-button"
                                                            data-id="choice-option-{{ $key }}-{{ $k }}"
                                                            type="button">
                                                            <i class="tio-remove font-weight-bold"></i>
                                                        </button>
                                                        <input type="number" name="choice-quantity{{ $k }}"
                                                            id="choice_quantity_input{{ $k }}"
                                                            class="form-control text-center border-0 h-100" placeholder="1"
                                                            value="1" min="1" max="9999999999" readonly>
                                                        <button class="btn btn-sm h-100 text-dark px-0 increase-button"
                                                            id="choice_quantity_button{{ $k }}"
                                                            data-id="choice-option-{{ $key }}-{{ $k }}"
                                                            type="button">
                                                            <i class="tio-add font-weight-bold"></i>
                                                        </button>
                                                    </label>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @php($add_ons = json_decode($product->add_ons))
                            @if (count($add_ons) > 0 && $add_ons[0] && (isset($choice->link_addons) ? $choice->link_addons == 'on' : true))
                                <div class="accordion" id="accordion{{ $key }}">
                                    <div id="header{{ $key }}" class="check-label mx-2 cursor-pointer rounded-sm">
                                        <div class="h3 p-2 d-flex justify-content-between align-items-center"
                                            data-toggle="collapse"
                                            data-target="#collapse{{ $key }}"
                                            aria-expanded="false"
                                            aria-controls="collapse{{ $key }}">
                                            <div>
                                                <span class="badge badge-info mr-2">{{ translate('messages.addon') }}</span>
                                                {{ translate('messages.for') }}
                                                <span class="text-primary">{{ translate('variation') . ' # ' . ++$index . ' ('. $choice->name .')' }}</span>
                                            </div>
                                            <div>
                                                <i class="tio-chevron-down"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="collapse{{ $key }}" class="collapse variation-addon-collapse mx-2 show"
                                        aria-labelledby="header{{ $key }}">
                                        <div class="d-flex justify-content-left flex-wrap variation-addon-container">
                                            @php($selected_variation_addons = isset($cart_item['variations'][$key]['addons']) ? $cart_item['variations'][$key]['addons'] : [])
                                            @php($selected_addon_ids = collect($selected_variation_addons)->pluck('id')->toArray())
                                            @php($selected_addon_qtys = collect($selected_variation_addons)->pluck('quantity', 'id')->toArray())

                                            @foreach (AddOn::whereIn('id', $add_ons)->active()->orderBy('name', 'asc')->get() as $add_on)
                                                <div class="col-4 pl-0 add-on-container relative">
                                                    <input type="hidden"
                                                        name="variation_addon_price[{{ $key }}][{{ $add_on->id }}]"
                                                        value="{{ $add_on->price }}">
                                                    <input class="btn-check addon-chek addon-quantity-input-toggle variation-addon-checkbox" type="checkbox"
                                                        id="variation_addon{{ $key }}_{{ $add_on->id }}"
                                                        name="variation_addon_id[{{ $key }}][]"
                                                        value="{{ $add_on->id }}"
                                                        {{ in_array($add_on->id, $selected_addon_ids) ? 'checked' : '' }}
                                                        autocomplete="off">
                                                    <label
                                                        class="d-flex flex-column justify-content-center text-left align-items-left btn btn-sm check-label mx-1 text-break variation-addon-label mb-4"
                                                        for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                        {{ Str::limit($add_on->name, 20, '...') }}
                                                        <br>
                                                        <span
                                                            class="text-warning font-weight-bold">{{ Helpers::format_currency($add_on->price) }}</span>
                                                    </label>
                                                    <label
                                                        class="input-group addon-quantity-input mx-1 shadow bg-white rounded border-0 variation-addon-quantity"
                                                        @if (in_array($add_on->id, $selected_addon_ids)) style="visibility:visible;" @else style="visibility:hidden;" @endif
                                                        for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                        <button class="btn btn-sm h-100 text-dark px-0 decrease-button variation-decrease-btn px-2"
                                                            data-id="{{ $add_on->id }}" type="button">
                                                            <i class="tio-remove font-weight-bold"></i>
                                                        </button>
                                                        <input type="number"
                                                            name="variation_addon_quantity[{{ $key }}][{{ $add_on->id }}]"
                                                            id="variation_addon_quantity_input{{ $key }}_{{ $add_on->id }}"
                                                            class="form-control text-center border-0 h-100 variation-addon-input"
                                                            placeholder="1" value="{{ $selected_addon_qtys[$add_on->id] ?? 1 }}"
                                                            min="1" max="9999999999" readonly>
                                                        <button class="btn btn-sm h-100 text-dark px-0 increase-button variation-increase-btn px-2"
                                                            id="variation_addon_quantity_button{{ $key }}_{{ $add_on->id }}"
                                                            data-id="{{ $add_on->id }}" type="button">
                                                            <i class="tio-add font-weight-bold"></i>
                                                        </button>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <input type="hidden" hidden name="option_ids" id="option_ids">

                    <!-- Quantity + Add to cart -->
                    <div class="d-flex justify-content-between mt-4">
                        <div class="product-description-label mt-2 text-dark h3">
                            {{ translate('messages.quantity') }}:
                        </div>
                        <div class="product-quantity d-flex align-items-center">
                            <div class="input-group input-group--style-2 pr-3 w-160px">
                                @php($disableMinus = false)
                                <span class="input-group-btn">
                                    <button class="btn btn-number text-dark" type="button"
                                        data-type="minus"
                                        data-field="quantity"
                                        data-editing="{{ (int)($cart_item['draft_product'] ?? 0) }}"
                                        {{ $disableMinus ? 'disabled' : '' }}
                                    >
                                        <i class="tio-remove font-weight-bold"></i>
                                    </button>
                                </span>
                                <label for="add_new_product_quantity">
                                </label>
                                <input id="add_new_product_quantity" type="text" name="quantity"
                                    class="form-control input-number text-center cart-qty-field" placeholder="1"
                                    value="{{ $cart_item['quantity'] }}" min="1" readonly
                                    data-maximum_cart_quantity='{{ min($product->maximum_cart_quantity ?? '9999999999', $product->stock_type == 'unlimited' ? '999999999' : $product->item_stock) }}'
                                    max="{{ $product->maximum_cart_quantity ?? '9999999999' }}">
                                <span class="input-group-btn">
                                    <button class="btn btn-number text-dark"
                                            type="button"
                                            id="quantity_increase_button"
                                            data-type="plus"
                                            data-field="quantity"
                                            data-editing="{{ $cart_item['draft_product'] ?? 0 }}"
                                    >
                                        <i class="tio-add font-weight-bold"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row no-gutters mt-2 text-dark" id="chosen_price_div">

                        <div class="col-2">
                            <div class="product-description-label">{{ translate('messages.Total_Price') }}:</div>
                        </div>
                        <div class="col-10">
                            <div class="product-price">
                                <strong id="chosen_price"></strong>
                            </div>
                        </div>
                    </div>
                    <div class="row no-gutters mt-2 text-dark" id="notes_div">
                        <div class="col-2">
                            <div class="product-description-label">{{ translate('messages.Notes') }}:</div>
                        </div>
                        <div class="col-10">
                            <div class="product-notes">
                                <!-- Editable Textarea for Notes -->
                                <textarea class="form-control" name="notes" id="notes" rows="3" placeholder="Enter any notes...">{{ old('notes', $cart_item['details'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-2">
                        @if ($product->stock_type !== 'unlimited' && $product->item_stock <= 0)
                            <a href="javascript:" data-product-id="{{ $item_key }}"
                                class="btn  btn--danger  remove-From-Cart"> {{ translate('Remove') }} <i
                                    class="tio-delete-outlined"></i></a>
                        @else
                            <button class="btn btn--primary h--45px w-40p add-To-Cart" type="button">
                                <i class="tio-shopping-cart"></i>
                                {{ translate('messages.update') }}
                            </button>
                        @endif
                    </div>                    
                </form>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    "use strict";
    cartQuantityInitialize();
    // getVariantPrice();
    calculateTotal(1);
    var finalTotal = 0;
    $('#add-to-cart-form input').on('change', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Initialize variation addon controls for cart item view
    function initializeVariationAddonControls() {
        // Handle variation addon checkbox changes
        $(document).off('change.variationAddonCart');
        $(document).on('change.variationAddonCart', 'input[name^="variation_addon_id"]', function() {
            var checkbox = $(this);
            var quantityContainer = checkbox.siblings('label').next('.variation-addon-quantity');

            if (checkbox.is(':checked')) {
                quantityContainer.css('visibility', 'visible');
            } else {
                quantityContainer.css('visibility', 'hidden');
            }
            // getVariantPrice();
            calculateTotal();
        });

        // Handle variation addon quantity controls
        $(document).off('click.variationAddonCart');
        $(document).on('click.variationAddonCart', '.variation-decrease-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var input = $(this).siblings('input[type="number"]');
            var currentValue = parseInt(input.val()) || 1;
            if (currentValue > 1) {
                input.val(currentValue - 1);
                // getVariantPrice();
                calculateTotal();
            }
        });

        $(document).on('click.variationAddonCart', '.variation-increase-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var input = $(this).siblings('input[type="number"]');
            var currentValue = parseInt(input.val()) || 1;
            input.val(currentValue + 1);
            // getVariantPrice();
            calculateTotal();
        });

        // Handle variation addon quantity input changes
        $(document).off('change.variationAddonCart');
        $(document).on('change.variationAddonCart', 'input[name^="variation_addon_quantity"]', function() {
            // getVariantPrice();
            calculateTotal();
        });
    }

    function getCheckedInputs() {
        var checkedInputs = [];
        var checkedElements = document.querySelectorAll('.input-element:checked');
        checkedElements.forEach(function(element) {
            checkedInputs.push(element.getAttribute('data-option_id'));
        });
        $('#option_ids').val(checkedInputs.join(','));

    }
    var inputElements = document.querySelectorAll('.input-element');
    inputElements.forEach(function(element) {
        element.addEventListener('change', getCheckedInputs);
    });

    // Initialize when document is ready
    $(document).ready(function() {
        initializeVariationAddonControls();
    });

    // Debug: Log form data before updating cart item
    $(document).on('click', '.add-To-Cart', function(e) {

        // Check the Calculation
        var isEditing = "{{ session()->has('is_editing_order') }}";
        if(isEditing){
            // Get the Current Total
            if(finalTotal < $('#cart_item_total_price').val()){
                Swal.fire({
                    icon: 'warning',
                    title: 'Not Allowed',
                    text: 'Sorry, you can not decrease the value'
                });
                return;
            }
        }

        var formData = $('#add-to-cart-form').serializeArray();
        console.log('Cart item update form data:', formData);

        // Check if variation addon data is present
        var variationAddonData = {};
        formData.forEach(function(item) {
            if (item.name.startsWith('variation_addon_')) {
                if (!variationAddonData[item.name]) {
                    variationAddonData[item.name] = [];
                }
                variationAddonData[item.name].push(item.value);
            }
        });
        console.log('Variation addon data for update:', variationAddonData);
    });

    function getCheckedPrice() 
    {
        // Reset arrays/totals every time function runs
        let selectedOptions = [];
        let addonsGrandTotal = 0;

        // ------------------------------
        // 1️⃣ Collect prices from variation options
        // ------------------------------
        var checkedOptions = document.querySelectorAll('.input-element:checked');

        checkedOptions.forEach(function(element) {
            const price = parseFloat(element.getAttribute('data-price')) || 0;
            selectedOptions.push(price);
        });

        // ------------------------------
        // 2️⃣ Collect prices × qty for addons
        // ------------------------------
        $('input.variation-addon-checkbox:checked').each(function () {
            const $checkbox = $(this);
            const container = $checkbox.closest('.add-on-container');

            // Hidden price input
            const price = parseFloat(
                container.find('input[name^="variation_addon_price"]').val()
            ) || 0;

            // Quantity input
            const qty = parseFloat(
                container.find('input[name^="variation_addon_quantity"]').val()
            ) || 1; // default 1

            const lineTotal = price * qty;
            addonsGrandTotal += lineTotal;
        });


        // ------------------------------
        // 3️⃣ SUM: option prices + addon totals
        // ------------------------------
        const optionsTotal = selectedOptions.reduce((a, b) => a + b, 0);
        const grandTotal = optionsTotal + addonsGrandTotal;

        // Return everything if you need detailed breakdown
        return {
            selectedOptionsTotal: optionsTotal,
            addonsTotal: addonsGrandTotal,
            total: grandTotal
        };
    }

    function calculateTotal(initial = 0) {
        // 1) Get totals from options + addons
        const value = getCheckedPrice(); // { selectedOptionsTotal, addonsTotal, total }
        const selectedOptionsTotal = value ? (value.selectedOptionsTotal || 0) : 0;
        const addonsTotal = value ? (value.addonsTotal || 0) : 0;
        const addonsAndOptionsTotal = value ? (value.total || 0) : 0;

        // 2) Base product price (without addons/options)
        const basePrice = parseFloat($('#base_price').val()) || 0;

        // Subtotal before discount
        let subTotal = (basePrice + selectedOptionsTotal) * $('#add_new_product_quantity').val();

        // 3) Get the discount and discount type from inputs
        const discountValue = parseFloat($('#product_discount').val()) || 0;
        const discountType  = $('#product_discount_type').val(); // "amount" or "percent"

        let discountAmount = 0;

        if (discountType === 'percent') {
            // % of subtotal
            discountAmount = subTotal * (discountValue / 100);
            $('#set-discount-amount').text(discountValue + '%' );
        } else {
            // fixed amount
            discountAmount = discountValue * $('#add_new_product_quantity').val();
            $('#set-discount-amount').text(discountAmount.toFixed(3) + 'ر.ع.‏' );
        }

        // 4) Safety: discount should not exceed subtotal
        if (discountAmount > subTotal) {
            discountAmount = subTotal;
        }

        // 5) Final total after discount
        finalTotal = subTotal - discountAmount + addonsTotal;

        if(initial){
            $('#cart_item_total_price').val(finalTotal.toFixed(3));
        }

        // 6) Update UI (adjust selectors to your HTML)
        // $('#product-price').text(finalTotal.toFixed(3) + 'ر.ع.‏');       // e.g. visible text
        $('#chosen_price').text(finalTotal.toFixed(3) + 'ر.ع.‏');       // hidden/input for form submit
    }

    (() => {
        const FORM_SELECTOR = '#add-to-cart-form';
        const FLAG_ID = 'options_changed';

        // ignore discount fields
        const IGNORE_IDS = new Set(['product_discount', 'product_discount_type']);

        const isIgnored = (el) => el && el.id && IGNORE_IDS.has(el.id);

        function ensureFlag(form) {
            let flag = form.querySelector(`#${FLAG_ID}`);
            if (!flag) {
            flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'options_changed';
            flag.id = FLAG_ID;
            flag.value = '0';
            form.appendChild(flag);
            }
            return flag;
        }

        function snapshot(form) {
            const entries = [];

            // 1) Normal fields (input/select/textarea)
            form.querySelectorAll('input, select, textarea').forEach(el => {
            if (!el.name) return;
            if (isIgnored(el)) return;

            const type = (el.type || '').toLowerCase();

            if (type === 'checkbox' || type === 'radio') {
                // handled below to include unchecked too
                return;
            }

            entries.push([el.name, String(el.value ?? '')]);
            });

            // 2) Include checkbox/radio checked state (including unchecked)
            form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(el => {
            if (!el.name) return;
            if (isIgnored(el)) return;

            // unique key per option
            const key = `__checked__${el.name}__${el.value}`;
            entries.push([key, el.checked ? '1' : '0']);
            });

            // stable order
            entries.sort((a, b) => (a[0] + a[1]).localeCompare(b[0] + b[1]));
            return JSON.stringify(entries);
        }

        function bind(form) {
            // prevent double binding
            if (form.dataset.changeTrackerBound === '1') return;
            form.dataset.changeTrackerBound = '1';

            const flag = ensureFlag(form);

            let initialState = '';

            const setDirty = (dirty) => {
            flag.value = dirty ? '1' : '0';
            };

            const captureBaseline = () => {
            initialState = snapshot(form);
            setDirty(false);
            };

            const recalcDirty = () => {
            const now = snapshot(form);
            setDirty(now !== initialState); // ✅ revert => back to 0
            };

            // IMPORTANT: capture baseline after render tick (for dynamic content)
            setTimeout(captureBaseline, 0);

            // Track changes (ignore discount)
            form.addEventListener('input', (e) => {
            if (isIgnored(e.target)) return;
            recalcDirty();
            }, true);

            form.addEventListener('change', (e) => {
            if (isIgnored(e.target)) return;
            recalcDirty();
            }, true);

            // If +/- buttons change values programmatically, recalc after click
            form.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            if (
                btn.matches('.btn-number, .variation-increase-btn, .variation-decrease-btn, .increase-button, .decrease-button')
            ) {
                setTimeout(recalcDirty, 0);
            }
            }, true);

            // Ensure correct value on submit
            form.addEventListener('submit', () => recalcDirty());

            // When modal is reopened and the same form is reused, reset baseline
            // (works for Bootstrap; harmless otherwise)
            document.addEventListener('shown.bs.modal', () => {
            if (document.contains(form)) setTimeout(captureBaseline, 0);
            });
        }

        function tryBind() {
            const form = document.querySelector(FORM_SELECTOR);
            if (form) bind(form);
        }

        // 1) bind if already exists
        tryBind();

        // 2) bind when injected later (AJAX modal)
        const obs = new MutationObserver(() => tryBind());
        obs.observe(document.documentElement, { childList: true, subtree: true });
        })();

</script>

<style>
    /* Variation-specific addon styling for cart item view */
    .variation-addon-container {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 0px;
        padding: 15px;
        border-left: 4px solid #17a2b8;
    }

    .variation-addon-label {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
        border: 2px solid #2196f3 !important;
        color: #1565c0 !important;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(33, 150, 243, 0.2);
    }

    .variation-addon-label:hover {
        background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
    }

    .variation-addon-label:active {
        transform: translateY(0);
        box-shadow: 0 1px 3px rgba(33, 150, 243, 0.4);
    }

    /* Hide the actual checkbox but keep it accessible */
    .variation-addon-checkbox {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .variation-decrease-btn,
    .variation-increase-btn {
        background: #ef7822 !important;
        color: white !important;
        border: none !important;
        transition: all 0.3s ease;
        border-radius: 0%;
    }

    .variation-decrease-btn:hover,
    .variation-increase-btn:hover {
        background: #ef7822 !important;
        transform: scale(1.1);
        border-radius: 0%;
    }

    /* Style for checked state */
    .variation-addon-checkbox:checked+.variation-addon-label {
        background: linear-gradient(135deg, #4caf50 0%, #45a049 100%) !important;
        border-color: #4caf50 !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4);
    }

    .variation-addon-quantity {
        transition: visibility 0.3s ease;
    }

    .variation-addon-input {
        background: #f8f9fa !important;
        color: #2e7d32 !important;
        font-weight: bold;
    }

    .badge-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        font-size: 0.8em;
        padding: 4px 8px;
        border-radius: 12px;
    }

    .text-primary {
        color: #007bff !important;
        font-weight: bold;
    }

    .text-success {
        color: #28a745 !important;
        font-weight: bold;
    }
</style>
