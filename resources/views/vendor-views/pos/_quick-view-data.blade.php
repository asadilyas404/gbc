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
                <h2>{{ translate('messages.description') }}</h2>
                <span class="d-block text-dark text-break">
                    {!! $product->description !!}
                </span>
                <form id="add-to-cart-form" class="mb-2">
                    @csrf
                    <input type="hidden" name="id" value="{{ $product->id }}">

                    <div class="d-flex justify-content-between mt-4">
                        <div class="product-description-label mt-2 text-dark h3">{{ translate('messages.Discount') }}:
                        </div>
                        <div class="form-group col-sm-4">
                            <input type="number" class="form-control" name="product_discount" min="0.0001"
                                id="product_discount" value="{{ $product->discount }}"
                                max="{{ $product['discount_type'] == 'percent' ? 100 : 1000000000 }}" step="0.0001">
                        </div>
                        <div class="form-group col-sm-4">
                            <select name="product_discount_type" class="form-control discount-type"
                                id="product_discount_type" onchange="getVariantPrice()">
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

                    {{-- Define add_ons variable at the top --}}
                    @php($add_ons = json_decode($product->add_ons))

                                    {{-- @php(dd($product->variations)); --}}

                    @foreach (json_decode($product->variations) as $key => $choice)
                        @if (isset($choice->price) == false)
                            <div class="h3 p-0 pt-2">{{ $choice->name }} <small class="text-muted fs-12">
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
                                    {{-- @php(dd($choice->values)); --}}
                                    @foreach ($choice->values as $k => $option)
                                        <div class="flex-column pb-2">
                                            <input
                                                class="btn-check input-element {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'stock_out' : '' }}"
                                                type="{{ $choice->type == 'multi' ? 'checkbox' : 'radio' }}"
                                                id="choice-option-{{ $key }}-{{ $k }}"
                                                data-option_id="{{ data_get($option, 'option_id') }}"
                                                name="variations[{{ $key }}][values][label][]"
                                                value="{{ $option->label }}"
                                                {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'disabled' : '' }}
                                                autocomplete="off">
                                                                            <label
                                    class="d-flex align-items-center btn btn-sm check-label mx-1 addon-input text-break {{ data_get($option, 'stock_type') && data_get($option, 'stock_type') !== 'unlimited' && data_get($option, 'current_stock') <= 0 ? 'stock_out text-muted' : 'text-dark' }}"
                                    for="choice-option-{{ $key }}-{{ $k }}">
                                    @if(isset($option->options_list_id) && $option->options_list_id)
                                        {{ Str::limit(OptionsList::find($option->options_list_id)->name ?? $option->label, 20, '...') }}
                                    @else
                                        {{ Str::limit($option->label, 20, '...') }}
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
                                    @endforeach
                                </div>

                                {{-- Addon selection for this variation --}}
                                @if (count($add_ons) > 0 && $add_ons[0])
                                    <div class="h3 p-0 pt-2 mt-3">
                                        <span class="badge badge-info mr-2">{{ translate('messages.addon') }}</span>
                                        {{ translate('messages.for') }} <span class="text-primary">{{ $choice->name }}</span>
                                    </div>
                                    <div class="d-flex justify-content-left flex-wrap variation-addon-container">
                                        @foreach (AddOn::whereIn('id', $add_ons)->active()->get() as $addon_key => $add_on)
                                            <div class="flex-column pb-1">
                                                <input type="hidden" name="variation_addon_price[{{ $key }}][{{ $add_on->id }}]"
                                                       value="{{ $add_on->price }}">
                                                <input class="btn-check addon-chek addon-quantity-input-toggle variation-addon-checkbox"
                                                       type="checkbox"
                                                       id="variation_addon{{ $key }}_{{ $add_on->id }}"
                                                       name="variation_addon_id[{{ $key }}][]"
                                                       value="{{ $add_on->id }}"
                                                       autocomplete="off">
                                                <label class="d-flex flex-column align-items-center align-middle btn btn-sm check-label mx-1 addon-input text-break variation-addon-label"
                                                       for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                    {{ Str::limit($add_on->name, 20, '...') }}
                                                    <br>
                                                    <span class="text-success font-weight-bold">{{ Helpers::format_currency($add_on->price) }}</span>
                                                </label>



                                                <label class="input-group addon-quantity-input mx-1 shadow bg-white rounded px-1 variation-addon-quantity"
                                                       for="variation_addon{{ $key }}_{{ $add_on->id }}">
                                                    <button class="btn btn-sm h-100 text-dark px-0 decrease-button variation-decrease-btn"
                                                            data-id="{{ $add_on->id }}" type="button">
                                                        <i class="tio-remove font-weight-bold"></i>
                                                    </button>
                                                    <input type="number"
                                                           name="variation_addon_quantity[{{ $key }}][{{ $add_on->id }}]"
                                                           id="variation_addon_quantity_input{{ $key }}_{{ $add_on->id }}"
                                                           class="form-control text-center border-0 h-100 variation-addon-input"
                                                           placeholder="1" value="1" min="1" max="9999999999" readonly>
                                                    <button class="btn btn-sm h-100 text-dark px-0 increase-button variation-increase-btn"
                                                            id="variation_addon_quantity_button{{ $key }}_{{ $add_on->id }}"
                                                            data-id="{{ $add_on->id }}" type="button">
                                                        <i class="tio-add font-weight-bold"></i>
                                                    </button>
                                                </label>
                                            </div>
                                        @endforeach
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
                                        data-field="quantity" disabled="disabled">
                                        <i class="tio-remove  font-weight-bold"></i>
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
                    @if (count($add_ons) > 0 && $add_ons[0])
                        <div class="h3 p-0 pt-2">{{ translate('messages.addon') }}</div>

                        <div class="d-flex justify-content-left flex-wrap">
                            @foreach (AddOn::whereIn('id', $add_ons)->active()->get() as $key => $add_on)
                                <div class="flex-column pb-2">
                                    <input type="hidden" name="addon-price{{ $add_on->id }}"
                                        value="{{ $add_on->price }}">
                                    <input class="btn-check addon-chek addon-quantity-input-toggle" type="checkbox"
                                        id="addon{{ $key }}" name="addon_id[]" value="{{ $add_on->id }}"
                                        autocomplete="off">
                                    <label
                                        class="d-flex align-items-center btn btn-sm check-label mx-1 addon-input text-break"
                                        for="addon{{ $key }}">{{ Str::limit($add_on->name, 20, '...') }}
                                        <br>
                                        {{ Helpers::format_currency($add_on->price) }}</label>
                                    <label class="input-group addon-quantity-input mx-1 shadow bg-white rounded px-1"
                                        for="addon{{ $key }}">
                                        <button class="btn btn-sm h-100 text-dark px-0 decrease-button"
                                            data-id="{{ $add_on->id }}" type="button"><i
                                                class="tio-remove  font-weight-bold"></i></button>
                                        <input type="number" name="addon-quantity{{ $add_on->id }}"
                                            id="addon_quantity_input{{ $add_on->id }}"
                                            class="form-control text-center border-0 h-100" placeholder="1"
                                            value="1" min="1" max="9999999999" readonly>
                                        <button class="btn btn-sm h-100 text-dark px-0 increase-button"
                                            id="addon_quantity_button{{ $add_on->id }}"
                                            data-id="{{ $add_on->id }}" type="button"><i
                                                class="tio-add  font-weight-bold"></i></button>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="row no-gutters d-none mt-2 text-dark" id="chosen_price_div">
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
                getVariantPrice();
            }
        });

        // Handle increase buttons for variation addons
        $(document).on('click.variationAddon', '.variation-increase-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var input = $(this).siblings('input[type="number"]');
            var currentValue = parseInt(input.val()) || 1;
            input.val(currentValue + 1);
            getVariantPrice();
        });

        // Handle variation addon checkbox changes
        $(document).on('change.variationAddon', 'input[name^="variation_addon_id"]', function() {
            getVariantPrice();
        });

        // Handle variation addon quantity input changes
        $(document).on('change.variationAddon', 'input[name^="variation_addon_quantity"]', function() {
            getVariantPrice();
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initializeVariationAddonControls();
        getVariantPrice(); // Calculate initial price
    });

    // Also initialize when modal is shown (in case of dynamic loading)
    $(document).on('shown.bs.modal', function() {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            initializeVariationAddonControls();
            getVariantPrice(); // Calculate price when modal is shown
        }, 100);
    });

    // Note: getVariantPrice is already called by specific event handlers above
    // This prevents duplicate calls when variation addons change

    // Debug: Log form data before submission
    $(document).on('click', '.add-To-Cart', function(e) {
        var formData = $('#add-to-cart-form').serializeArray();
        console.log('Form data being sent:', formData);

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
        console.log('Variation addon data:', variationAddonData);
    });

    // Enhanced getVariantPrice function to handle variation-specific addons
    function getVariantPrice() {
        getCheckedInputs();
        if ($('#add-to-cart-form input[name=quantity]').val() > 0 ) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.ajax({
                type: "POST",
                url: '{{ route('vendor.pos.variant_price') }}',
                data: $('#add-to-cart-form').serializeArray(),
                success: function (data) {
                    if (data.error === 'quantity_error') {
                        toastr.error(data.message);
                    }
                    else if(data.error === 'stock_out'){
                        toastr.warning(data.message);
                        if(data.type == 'addon'){
                            $('#addon_quantity_button'+data.id).attr("disabled", true);
                            $('#addon_quantity_input'+data.id).val(data.current_stock);
                        }
                        else{
                            $('.input-element[data-option_id="'+data.id+'"]').attr("disabled", true);
                        }
                    }
                    else {
                        // Update the price display
                        $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                        $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
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
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
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
    border: 2px solid #4caf50 !important;
    background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%) !important;
    box-shadow: 0 2px 4px rgba(76, 175, 80, 0.2);
}

.variation-decrease-btn,
.variation-increase-btn {
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%) !important;
    color: white !important;
    border: none !important;
    transition: all 0.3s ease;
}

.variation-decrease-btn:hover,
.variation-increase-btn:hover {
    background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%) !important;
    transform: scale(1.1);
}

.variation-addon-input {
    background: #f8f9fa !important;
    border: 1px solid #4caf50 !important;
    color: #2e7d32 !important;
    font-weight: bold;
}

/* Checkbox styling for variation addons */
.variation-addon-checkbox:checked + .variation-addon-label {
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
