@php
    use App\CentralLogics\Helpers;
    use App\Models\AddOn;
    use App\Models\OptionsList;
@endphp
<div class="initial-49">
    <div class="modal-header p-0">
        <h4 class="modal-title product-title">
        </h4>
        <button class="close call-when-done" type="button" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
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
                    <span class="fz-12px line-through" id="original-price">
                        {{ Helpers::get_price_range($product) }}
                    </span>
                    @endif --}}
                    <span
                        class="fz-12px line-through {{ $product->discount > 0 || Helpers::get_restaurant_discount($product->restaurant) ? '' : 'd-none' }}"
                        id="original-price">
                        {{ Helpers::get_price_range($product) }}
                    </span>
                </div>
                {{-- @if ($product->discount > 0) --}}
                <div class="mb-3 text-dark">
                    <strong>{{ translate('messages.discount') }} : </strong>
                    <strong id="set-discount-amount">{{ Helpers::get_product_discount($product) }}</strong>
                </div>
                {{-- @endif --}}
            </div>
        </div>

        <div class="row pt-2">
            <div class="col-12">
                <?php
                $cart = false;
                if (session()->has('cart')) {
                    foreach (session()->get('cart') as $key => $cartItem) {
                        if (is_array($cartItem) && $cartItem['id'] == $product['id']) {
                            $cart = $cartItem;
                        }
                    }
                }
                ?>
                @if(!empty($product->description))
                    <h2>{{ translate('messages.description') }}</h2>
                    <span class="d-block text-dark text-break">
                        {!! $product->description !!}
                    </span>
                @endif
                <form id="add-to-cart-form" class="mb-2">
                    @csrf
                    <input type="hidden" name="id" value="{{ $product->id }}">
                    <input type="hidden" name="partner_id" value="{{ $partner_id }}">
                    <input type="hidden" name="base_price" id="base_price" value="{{ $product->price }}">

                    <div class="row justify-content-between mt-4">
                        <div class="product-description-label mt-2 text-dark h4 col-12">
                            {{ translate('messages.Discount') }}:
                        </div>
                        <div class="form-group col-md-6">
                            <input type="number" class="form-control" name="product_discount" min="0.0001"
                                id="product_discount" value="{{ $product->discount }}"
                                onkeyup="calculateTotal()"
                                max="{{ $product['discount_type'] == 'percent' ? 100 : 1000000000 }}" step="0.0001">
                        </div>
                        <div class="form-group col-md-6">
                            <select name="product_discount_type" class="form-control discount-type"
                                id="product_discount_type" onchange="calculateTotal()">
                                <option value="amount" {{ $product['discount_type'] == 'amount' ? 'selected' : '' }}>
                                    {{ translate('messages.amount') }}
                                    ({{ Helpers::currency_symbol() }})
                                </option>
                                <option value="percent" {{ $product['discount_type'] == 'percent' ? 'selected' : '' }}>
                                    {{ translate('messages.percent') }}
                                    (%)
                                </option>
                            </select>
                        </div>
                    </div>

                    @php ($add_ons = json_decode($product->add_ons)); $index = 0; @endphp

                    @foreach (json_decode($product->variations) as $key => $choice)
                        @if (isset($choice->price) == false)
                            @php $title = translate('variation') . ' # ' . ++$index . ' ('. $choice->name .')';  @endphp
                            <div class="h3 p-0 pt-2">{{ $title }} <small class="text-muted fs-12">
                                    ({{ $choice->required == 'on' ? translate('messages.Required') : translate('messages.optional') }}
                                    ) </small>
                            </div>
                            @if ($choice->min != 0 && $choice->max != 0)
                                <small class="d-block mb-3">
                                    {{ translate('You_need_to_select_minimum_ ') }} {{ $choice->min }}
                                    {{ translate('to_maximum_ ') }} {{ $choice->max }} {{ translate('options') }}
                                </small>
                            @endif

                            <div>
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
                                        @php
                                            $showOption = true;
                                            if (isset($option->options_list_id) && $option->options_list_id) {
                                                $optionsList = OptionsList::find($option->options_list_id);
                                                $showOption = $optionsList && $optionsList->status == 1;
                                            }
                                        @endphp
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
                                                    +{{ Helpers::format_currency(data_get($option, 'optionPrice')) }}
                                                    <span
                                                        class="input-label-secondary text--title text--warning {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? '' : 'd-none' }}"
                                                        title="{{ translate('Currently_you_need_to_manage_discount_with_the_Restaurant.') }}">
                                                        <i class="tio-info-outlined"></i>
                                                        <small>{{ translate('stock_out') }}</small>
                                                    </span>
                                                </label>
                                                @if ($choice->type == 'multi')
                                                    <label
                                                        class="d-none input-group addon-quantity-input mx-1 shadow bg-white rounded px-1"
                                                        for="choice-option-{{ $key }}-{{ $k }}">
                                                        <button class="btn btn-sm h-100 text-dark px-0 decrease-button"
                                                            data-id="choice-option-{{ $key }}-{{ $k }}"
                                                            type="button">
                                                            <i class="tio-remove font-weight-bold"></i>
                                                        </button>
                                                        <input type="number" name="choice-quantity{{ $k }}"
                                                            id="choice_quantity_input{{ $k }}"
                                                            class="form-control text-center border-0 h-100"
                                                            placeholder="1" value="1" min="1"
                                                            max="9999999999" readonly>
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

                                {{-- Addon selection for this variation --}}
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
                                                    <span class="text-primary">{{ $title }}</span>
                                                </div>
                                                <div>
                                                    <i class="tio-chevron-down"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="collapse{{ $key }}" class="collapse variation-addon-collapse mx-2"
                                            aria-labelledby="header{{ $key }}">
                                            <div 
                                                class="d-flex justify-content-left flex-wrap variation-addon-container"
                                                aria-labelledby="header{{ $key }}"
                                                data-parent="#accordion{{ $key }}">

                                                @foreach (AddOn::whereIn('id', $add_ons)->active()->orderBy('name', 'asc')->get() as $addon_key => $add_on)
                                                    <div class="col-4 pl-0 add-on-container relative">
                                                        <input type="hidden"
                                                            name="variation_addon_price[{{ $key }}][{{ $add_on->id }}]"
                                                            value="{{ $add_on->price }}">

                                                        <input
                                                            class="btn-check addon-chek addon-quantity-input-toggle variation-addon-checkbox"
                                                            type="checkbox"
                                                            id="variation_addon{{ $key }}_{{ $add_on->id }}"
                                                            name="variation_addon_id[{{ $key }}][]"
                                                            value="{{ $add_on->id }}"
                                                            autocomplete="off">

                                                        <label
                                                            class="d-flex flex-column justify-content-center text-left align-items-left btn btn-sm check-label mx-1 text-break variation-addon-label mb-4"
                                                            for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                            {{ Str::limit($add_on->name, 20, '...') }}
                                                            <br>
                                                            <span class="text-warning font-weight-bold">
                                                                {{ Helpers::format_currency($add_on->price) }}
                                                            </span>
                                                        </label>

                                                        <label
                                                            class="absolute input-group addon-quantity-input mx-1 shadow bg-white border-0 variation-addon-quantity"
                                                            for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                            <button
                                                                class="btn btn-sm h-100 text-dark px-0 decrease-button variation-decrease-btn px-2"
                                                                data-id="{{ $add_on->id }}" type="button">
                                                                <i class="tio-remove font-weight-bold"></i>
                                                            </button>

                                                            <input type="number"
                                                                name="variation_addon_quantity[{{ $key }}][{{ $add_on->id }}]"
                                                                id="variation_addon_quantity_input{{ $key }}_{{ $add_on->id }}"
                                                                class="form-control text-center border-0 h-100 variation-addon-input border-0"
                                                                placeholder="1" value="1" min="1"
                                                                max="9999999999" readonly>

                                                            <button
                                                                class="btn btn-sm h-100 text-dark px-0 increase-button variation-increase-btn px-2"
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
                            </div>
                        @endif
                    @endforeach

                    <input type="hidden" hidden name="option_ids" id="option_ids">

                    <div class="d-flex justify-content-between mt-1">
                        <div class="product-description-label mt-2 text-dark h3">{{ translate('messages.quantity') }}:
                        </div>
                        <div class="product-quantity d-flex align-items-center">
                            <div class="input-group input-group--style-2 pr-3 w-160px">
                                <span class="input-group-btn">
                                    <button class="btn btn-number text-dark p--10px" type="button" data-type="minus"
                                        data-field="quantity">
                                        <i class="tio-remove font-weight-bold"></i>
                                    </button>
                                </span>
                                <input type="text" name="quantity" id="add_new_product_quantity"
                                    class="form-control input-number text-center cart-qty-field" placeholder="1"
                                    value="1" min="1"
                                    data-maximum_cart_quantity='{{ min($product->maximum_cart_quantity ?? '9999999999', $product->stock_type == 'unlimited' ? '999999999' : $product->item_stock) }}'
                                    max="{{ $product->maximum_cart_quantity ?? '9999999999' }}">
                                <span class="input-group-btn">
                                    <button class="btn btn-number text-dark p--10px" id="quantity_increase_button"
                                        type="button" data-type="plus" data-field="quantity">
                                        <i class="tio-add  font-weight-bold"></i>
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
                                <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Enter any notes..."></textarea>
                            </div>
                        </div>
                    </div>


                    <div class="d-flex justify-content-center mt-2">
                        @if ($product->stock_type !== 'unlimited' && $product->item_stock <= 0)
                            <button class="btn btn-secondary h--45px w-40p " type="button">
                                <i class="tio-shopping-cart"></i>
                                {{ translate('messages.Out_Of_Stock') }}
                            </button>
                        @else
                            <button class="btn btn--primary h--45px w-40p add-To-Cart" type="button">
                                <i class="tio-shopping-cart"></i>
                                {{ translate('messages.add_to_cart') }}
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
    // Initialize variation-specific addon quantity controls
    function initializeVariationAddonControls() {
        // Remove any existing event handlers first
        $(document).off('click.variationAddon');
        $(document).off('change.variationAddon');

        // Handle decrease buttons for variation addons
        $(document).on('click.variationAddon', '.variation-decrease-btn', function(e) {
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

        // Handle increase buttons for variation addons
        $(document).on('click.variationAddon', '.variation-increase-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var input = $(this).siblings('input[type="number"]');
            var currentValue = parseInt(input.val()) || 1;
            input.val(currentValue + 1);
            // getVariantPrice();
            calculateTotal();
        });

        // Handle variation addon checkbox changes
        $(document).on('change.variationAddon', 'input[name^="variation_addon_id"]', function() {
            // getVariantPrice();
            calculateTotal();
        });

        // Handle variation addon quantity input changes
        $(document).on('change.variationAddon', 'input[name^="variation_addon_quantity"]', function() {
            // getVariantPrice();
            calculateTotal();
        });
    }

    // // Initialize when document is ready
    // $(document).ready(function() {
    //     initializeVariationAddonControls();
    //     getVariantPrice(); // Calculate initial price
    // });

    // Also initialize when modal is shown (in case of dynamic loading)
    $(document).on('shown.bs.modal', function() {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            initializeVariationAddonControls();
            // getVariantPrice(); // Calculate price when modal is shown
            calculateTotal();
        }, 100);
    });

    function calculateTotal() {
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
            discountAmount = discountValue;
            $('#set-discount-amount').text(discountAmount.toFixed(3) + 'ر.ع.‏' );
        }

        // 4) Safety: discount should not exceed subtotal
        if (discountAmount > subTotal) {
            discountAmount = subTotal;
        }

        // 5) Final total after discount
        const finalTotal = subTotal - discountAmount + addonsTotal;

        // 6) Update UI (adjust selectors to your HTML)
        // $('#product-price').text(finalTotal.toFixed(3) + 'ر.ع.‏');       // e.g. visible text
        $('#chosen_price').text(finalTotal.toFixed(3) + 'ر.ع.‏');       // hidden/input for form submit
    }


    // Add event handlers for all input changes to update price
    $(document).on('change', '#add-to-cart-form input[type="radio"], #add-to-cart-form input[type="checkbox"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Handle quantity changes
    $(document).on('change', '#add-to-cart-form input[name="quantity"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Handle discount changes
    $(document).on('input change keydown', '#add-to-cart-form select[name="product_discount"] ,#add-to-cart-form select[name="product_discount_type"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Handle addon changes
    $(document).on('change', '#add-to-cart-form input[name="addon_id[]"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Handle addon quantity changes
    $(document).on('change', '#add-to-cart-form input[name^="addon-quantity"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Handle choice quantity changes for multi-select variations
    $(document).on('change', '#add-to-cart-form input[name^="choice-quantity"]', function() {
        // getVariantPrice();
        calculateTotal();
    });

    // Enhanced getVariantPrice function to handle variation-specific addons
    var variantPriceRequest = null;
    function getVariantPrice() {
        getCheckedInputs();
        if ($('#add-to-cart-form input[name=quantity]').val() > 0) {
            
            if (variantPriceRequest !== null) {
                variantPriceRequest.abort();
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            variantPriceRequest = $.ajax({
                type: "POST",
                url: '{{ route('vendor.pos.variant_price') }}',
                data: $('#add-to-cart-form').serializeArray(),
                beforeSend: function(){
                    $('#loading').show();
                },
                success: function(data) {
                    $('#loading').hide();
                    if (data.error === 'quantity_error') {
                        toastr.error(data.message);
                    } else if (data.error === 'stock_out') {
                        toastr.warning(data.message);
                        if (data.type == 'addon') {
                            $('#addon_quantity_button' + data.id).attr("disabled", true);
                            $('#addon_quantity_input' + data.id).val(data.current_stock);
                        } else {
                            $('.input-element[data-option_id="' + data.id + '"]').attr("disabled", true);
                        }
                    } else {
                        // Update the price display
                        $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                        $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                        $('.add-To-Cart').removeAttr("disabled");
                        $('.increase-button').removeAttr("disabled");
                        $('#quantity_increase_button').removeAttr("disabled");
                    }
                },
                error: function(xhr, status) {
                    if (status === "abort") return; // ignore aborted requests
                    $('#loading').hide();
                    toastr.error('Something went wrong. Please try again.');
                }
            });
        }
    }

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
</script>

<style>
    /* Variation-specific addon styling */
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

    .variation-addon-quantity {
        transition: visibility 0.3s ease;
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

    .variation-addon-input {
        background: #f8f9fa !important;
        color: #2e7d32 !important;
        font-weight: bold;
    }

    /* Checkbox styling for variation addons */
    .variation-addon-checkbox:checked+.variation-addon-label {
        background: linear-gradient(135deg, #4caf50 0%, #45a049 100%) !important;
        color: white !important;
        border-color: #2e7d32 !important;
        transform: scale(1.05);
    }

    /* Badge styling */
    .badge-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
        color: white;
        font-size: 0.8em;
        padding: 5px 10px;
        border-radius: 15px;
    }

    /* Variation name styling */
    .text-primary {
        color: #007bff !important;
        font-weight: bold;
        text-shadow: 0 1px 2px rgba(0, 123, 255, 0.1);
    }

    /* Price styling */
    .text-success {
        color: #28a745 !important;
        font-weight: bold;
        text-shadow: 0 1px 2px rgba(40, 167, 69, 0.1);
    }

    /* Container hover effect */
    .variation-addon-container:hover {
        box-shadow: 0 4px 12px rgba(23, 162, 184, 0.15);
        transform: translateY(-1px);
        transition: all 0.3s ease;
    }
</style>
