<div class="d-flex flex-row initial-47">
    <table class="table table-align-middle">
        <thead class="thead-light border-0 text-center">
            <tr>
                <th class="py-2" scope="col">{{ translate('messages.item') }}</th>
                <th class="py-2" scope="col" class="text-center">{{ translate('messages.qty') }}</th>
                <th class="py-2" scope="col">{{ translate('messages.price') }}</th>
                <th class="py-2" scope="col">{{ translate('messages.delete') }}</th>
            </tr>
        </thead>
        <tbody>
            <?php
            use App\CentralLogics\Helpers;
            $subtotal = 0;
            $addon_price = 0;
            $tax = Helpers::get_restaurant_data()->tax;
            $discount = 0;
            $discount_type = 'amount';
            $discount_on_product = 0;
            $variation_price = 0;
            ?>
            @if (session()->has('cart') && count(session()->get('cart')) > 0)
                <?php
                $cart = session()->get('cart');
                if (isset($cart['tax'])) {
                    $tax = $cart['tax'];
                }
                if (isset($cart['discount'])) {
                    $discount = $cart['discount'];
                    $discount_type = $cart['discount_type'];
                }
                ?>
                @foreach (session()->get('cart') as $key => $cartItem)
                    @if (is_array($cartItem))
                        <?php
                        $variation_price += $cartItem['variation_price'];
                        $product_subtotal = $cartItem['price'] * $cartItem['quantity'];
                        $discount_on_product += $cartItem['discount'] * $cartItem['quantity'];
                        $subtotal += $product_subtotal;
                        $addon_price += $cartItem['addon_price'];
                        ?>
                        <tr>
                            <td class="media cart--media align-items-center cursor-pointer quick-View-Cart-Item"
                                data-product-id="{{ $cartItem['id'] }}" data-item-key="{{ $key }}">
                                <img class="avatar avatar-sm mr-2 onerror-image" src="{{ $cartItem['image_full_url'] }}"
                                    data-onerror-image="{{ dynamicAsset('public/assets/admin/img/160x160/img2.jpg') }}"
                                    alt="{{ data_get($cartItem, 'image') }} image">


                                <div class="media-body">
                                    <h5 class="text-hover-primary mb-0">{{ Str::limit($cartItem['name'], 10) }}</h5>
                                    <small>{{ Str::limit($cartItem['variant'], 20) }}</small>
                                </div>
                            </td>
                            <td class="align-items-center text-center">
                                <label>
                                    <input type="number" data-key="{{ $key }}"
                                        data-value="{{ $cartItem['quantity'] }}"
                                        data-option_ids="{{ $cartItem['variation_option_ids'] }}"
                                        data-food_id="{{ $cartItem['id'] }}"
                                        class="w-50px text-center rounded border  update-Quantity"
                                        value="{{ $cartItem['quantity'] }}" min="1"
                                        max="{{ $cartItem['maximum_cart_quantity'] ?? '9999999999' }}">
                                </label>
                            </td>
                            <td class="text-center px-0 py-1">
                                <div class="btn">
                                    {{ Helpers::format_currency(round($product_subtotal, 3)) }}
                                </div>
                            </td>
                            <td class="align-items-center">
                                <div class="btn--container justify-content-center">
                                    <a href="javascript:" data-product-id="{{ $key }}"
                                        class="btn btn-sm btn--danger action-btn btn-outline-danger remove-From-Cart">
                                        <i class="tio-delete-outlined"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
        </tbody>
    </table>
</div>

<?php
$add = false;
if (session()->has('address') && count(session()->get('address')) > 0) {
    $add = true;
    $delivery_fee = session()->get('address')['delivery_fee'];
} else {
    $delivery_fee = 0;
}
$total = $subtotal + $addon_price;
$discount_amount = $discount_type == 'percent' && $discount > 0 ? (($total - $discount_on_product) * $discount) / 100 : $discount;
$total -= $discount_amount + $discount_on_product;
$tax_included = Helpers::get_mail_status('tax_included') ?? 0;
$total_tax_amount = $tax > 0 ? ($total * $tax) / 100 : 0;

$tax_a = $total_tax_amount;
if ($tax_included == 1) {
    $tax_a = 0;
}
$additional_charge = 0.0;
if (Helpers::get_business_data('additional_charge_status')) {
    $additional_charge = Helpers::get_business_data('additional_charge');
}

$total = $total + $delivery_fee;
if (isset($cart['paid'])) {
    $paid = $cart['paid'];
    $change = $total + $tax_a + $additional_charge - $paid;
} else {
    $paid = $total + $tax_a + $additional_charge;
    $change = 0;
}
?>
<form action="{{ route('vendor.pos.order') }}" id='order_place' method="post">
    @csrf
    <input type="hidden" name="user_id" id="customer_id">
    <div class="box p-3">
        <dl class="row">

            <dt class="col-6 font-regular">{{ translate('messages.addon') }}:</dt>
            <dd class="col-6 text-right">{{ Helpers::format_currency(round($addon_price, 3)) }}</dd>

            <dt class="col-6 font-regular">{{ translate('messages.subtotal') }}

                @if ($tax_included == 1)
                    ({{ translate('messages.TAX_Included') }})
                @endif
                :
            </dt>
            <dd class="col-6 text-right">{{ Helpers::format_currency(round($subtotal + $addon_price, 3)) }}</dd>


            <dt class="col-6 font-regular">{{ translate('messages.discount') }} :</dt>
            <dd class="col-6 text-right">- {{ Helpers::format_currency(round($discount_on_product, 3)) }}</dd>
            <dt class="col-6 font-regular">{{ translate('messages.delivery_fee') }} :</dt>
            <dd class="col-6 text-right" id="delivery_price">
                {{ Helpers::format_currency(round($delivery_fee, 3)) }}</dd>

            <dt class="col-6 font-regular">{{ translate('messages.extra_discount') }} :</dt>
            <dd class="col-6 text-right">
                <button class="btn btn-sm" type="button" data-toggle="modal" data-target="#add-discount"><i
                        class="tio-edit"></i></button>
                - {{ Helpers::format_currency(round($discount_amount, 3)) }}
            </dd>

            @if ($tax_included != 1)
                <dt class="col-6 font-regular">{{ translate('messages.vat/tax') }}:</dt>
                <dd class="col-6 text-right">
                    <button class="btn btn-sm" type="button" data-toggle="modal" data-target="#add-tax"><i
                            class="tio-edit"></i></button>
                    +
                    {{ Helpers::format_currency(round($total_tax_amount, 3)) }}
                </dd>
            @endif

            @if (\App\CentralLogics\Helpers::get_business_data('additional_charge_status'))
                <dt class="col-6 font-regular">
                    {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('messages.additional_charge') }}
                    :</dt>
                <dd class="col-6 text-right">
                    @if ($subtotal + $addon_price > 0)
                        {{ Helpers::format_currency(round($additional_charge, 3)) }}
                    @else
                        {{ Helpers::format_currency($additional_charge) }}
                    @endif
                </dd>
            @endif


            <dd class="col-12">
                <hr class="m-0">
            </dd>
            <dt class="col-6 font-regular">{{ translate('Total') }}:</dt>
            <dd class="col-6 text-right h4 b">
                {{ Helpers::format_currency(round($total + $additional_charge + $tax_a, 3)) }} </dd>
        </dl>
        {{-- <div class="pos--payment-options mt-3 mb-3">
            <h5 class="mb-3">{{ translate($add ? 'messages.Payment Method' : 'Paid by') }}</h5>
            <ul>
                @if ($add)
                    @php($cod = Helpers::get_business_settings('cash_on_delivery'))
                    @if ($cod['status'])
                        <li>
                            <label>
                                <input type="radio" name="type" value="cash" hidden checked>
                                <span>{{ translate('Cash_On_Delivery') }}</span>
                            </label>
                        </li>
                    @endif
                @else
                    <li>
                        <label>
                            <input type="radio" name="type" value="cash" hidden="" checked>
                            <span>{{ translate('messages.Cash') }}</span>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="radio" name="type" value="card" hidden="">
                            <span>{{ translate('messages.Card') }}</span>
                        </label>
                    </li>
                @endif

            </ul>
        </div> --}}

        {{-- <div id="cashCardFields" style="display: none; margin-top: 10px;">
            <div>
                <input type="number" id="cashAmount" name="cashAmount" class="form-control" min="0" step="0.01" placeholder="{{ translate('messages.Enter Cash Amount') }}">
            </div>
            <div>
                <input type="number" id="cardAmount" name="cardAmount" class="form-control mt-1" min="0" step="0.01" placeholder="{{ translate('messages.Enter Card Amount') }}">
            </div>
        </div> --}}

        {{-- @if (!$add)
            <div class="mt-4 d-flex justify-content-between pos--payable-amount">
                <label class="m-0">{{ translate('Paid Amount') }} :</label>
                <div>
                    <span data-toggle="modal" data-target="#insertPayableAmount" class="text-body"><i
                            class="tio-edit"></i></span>
                    <span>{{ Helpers::format_currency($paid) }}</span>
                    <input type="hidden" name="amount" value="{{ $paid }}">
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-between pos--payable-amount">
                <label class="mb-1">{{ translate('Change Amount') }} :</label>
                <div>
                    <span>{{ Helpers::format_currency($change) }}</span>
                    <input type="hidden" value="{{ $change }}">
                </div>
            </div>
        @endif --}}
        <div class="row button--bottom-fixed g-1 bg-white">
            <div class="col-sm-6">
                <button type="button" data-toggle="modal" data-target="#orderFinalModal"
                    class="btn btn--primary btn-sm btn-block">{{ translate('proceed') }} </button>
            </div>

            {{-- <div class="col-sm-6">
                <button type="submit"
                        class="btn  btn--primary btn-sm btn-block">{{ translate('place_order') }} </button>
            </div> --}}
            <div class="col-sm-6">
                <a href="#" class="btn btn--reset btn-sm btn-block empty-Cart">{{ translate('Clear_Cart') }}</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderFinalModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light py-3">
                    <h4 class="modal-title">{{ translate('Payment Details') }}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding-top: 20px;">
                    <!-- Top Cards Section -->
                    <div class="row mb-4">
                        <div class="col-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>{{ translate('Invoice Amount') }}</h5>
                                    <h4 id="invoice_amount" class="font-weight-bold">
                                        <span>{{ Helpers::format_currency($paid) }}</span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>{{ translate('Cash Paid') }}</h5>
                                    <h4 id="cash_paid_display" class="font-weight-bold">
                                        {{ Helpers::format_currency(0.0) }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>{{ translate('Cash Return') }}</h5>
                                    <h4 id="cash_return" class="font-weight-bold">
                                        {{ Helpers::format_currency(0.0) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Details Section -->
                    <div class="row pl-2 mt-3">
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="customer_name" class="input-label">
                                    {{ translate('Customer Name') }}
                                </label>
                                <input id="customer_name" type="text" name="customer_name" class="form-control"
                                    value="{{ old('customer_name') }}"
                                    placeholder="{{ translate('Customer Name') }}">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="car_number" class="input-label">{{ translate('Car Number') }}</label>
                                <input id="car_number" type="text" name="car_number" class="form-control"
                                    value="{{ old('car_number') }}" placeholder="{{ translate('Car Number') }}">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="phone" class="input-label">
                                    {{ translate('Phone') }} ({{ translate('with_country_code') }})
                                </label>
                                <input id="phone" type="tel" name="phone" class="form-control"
                                    value="{{ old('phone') }}" placeholder="{{ translate('Phone') }}">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="row pl-2">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="cash_paid"
                                            class="input-label">{{ translate('Cash Amount') }}</label>
                                        <input id="cash_paid" type="text" name="cash_paid" class="form-control"
                                            min="0" step="0.001"
                                            placeholder="{{ translate('Enter cash amount') }}">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="delivery_type" class="input-label">Delivery Type</label>
                                        <select id="delivery_type" name="delivery_type" class="form-control">
                                            <option value="dine_in" selected>Dine In</option>
                                            <option value="take_away">Take away</option>
                                            <option value="delivery">Delivery</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="card_paid"
                                            class="input-label">{{ translate('Card Amount') }}</label>
                                        <input id="card_paid" type="text" name="card_paid" class="form-control"
                                            min="0" step="0.001"
                                            placeholder="{{ translate('Enter card amount') }}">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="bank_account"
                                            class="input-label">{{ translate('Select Account') }}</label>
                                        <select id="bank_account" name="bank_account" class="form-control" disabled>
                                            <option value="">{{ translate('Select an option') }}</option>
                                            <option value="1">{{ translate('Bank 1') }}</option>
                                            <option value="2">{{ translate('Bank 2') }}</option>
                                            <option value="3">{{ translate('Bank 3') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <!-- Submit Button -->
                                <div class="btn--container justify-content-end mt-4">
                                    <button type="button" class="btn btn-secondary"
                                        data-dismiss="modal">{{ translate('Close') }}</button>
                                    <button type="submit"
                                        class="btn btn--primary">{{ translate('Place Order') }}</button>
                                </div>
                            </div>
                        </div>
                        <!-- Compact Numeric Keypad -->
                        <div class="col-lg-4">
                            <div class="numeric-keypad-container p-2 border rounded bg-light">
                                <h6 class="text-center">{{ translate('Numeric Keypad') }}</h6>
                                <div class="keypad-buttons d-flex flex-wrap justify-content-center">
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="1">1</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="2">2</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="3">3</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="4">4</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="5">5</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="6">6</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="7">7</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="8">8</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="9">9</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value="0">0</button>
                                    <button type="button" class="btn btn-outline-dark keypad-btn"
                                        data-value=".">.</button>
                                    <button type="button"
                                        class="btn btn-outline-danger keypad-clear">{{ translate('C') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</form>

<div class="modal fade" id="insertPayableAmount" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title">{{ translate('messages.payment') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id='payable_store_amount'>
                    @csrf
                    <div class="row">
                        <div class="form-group col-12">
                            <label class="input-label"
                                for="paid">{{ translate('messages.amount') }}({{ Helpers::currency_symbol() }})</label>
                            <input id="paid" type="number" class="form-control" name="paid" min="0"
                                step="0.01" value="{{ $paid }}">
                        </div>
                    </div>
                    <div class="form-group col-12 mb-0">
                        <div class="btn--container justify-content-end">
                            <button class="btn btn-sm btn--primary payable-Amount" type="button">
                                {{ translate('messages.submit') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="add-discount" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('messages.update_discount') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('vendor.pos.discount') }}" method="post" class="row">
                    @csrf
                    <div class="form-group col-sm-6">
                        <label for="discount_input">{{ translate('messages.discount') }}</label>
                        <input type="number" class="form-control" name="discount" min="0.0001"
                            id="discount_input" value="{{ $discount }}"
                            max="{{ $discount_type == 'percent' ? 100 : 1000000000 }}" step="0.0001">
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="discount_input_type">{{ translate('messages.type') }}</label>
                        <select name="type" class="form-control discount-type" id="discount_input_type">
                            <option value="amount" {{ $discount_type == 'amount' ? 'selected' : '' }}>
                                {{ translate('messages.amount') }}
                                ({{ Helpers::currency_symbol() }})
                            </option>
                            <option value="percent" {{ $discount_type == 'percent' ? 'selected' : '' }}>
                                {{ translate('messages.percent') }}
                                (%)
                            </option>
                        </select>
                    </div>
                    <div class="form-group col-sm-12">
                        <button class="btn btn-sm btn--primary"
                            type="submit">{{ translate('messages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="add-tax" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('messages.update_tax') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('vendor.pos.tax') }}" method="POST" class="row" id="order_submit_form">
                    @csrf
                    <div class="form-group col-12">
                        <label for="tax">{{ translate('messages.tax') }}(%)</label>
                        <input id="tax" type="number" class="form-control" max="100" name="tax"
                            min="0">
                    </div>

                    <div class="form-group col-sm-12">
                        <button class="btn btn-sm btn--primary"
                            type="submit">{{ translate('messages.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title flex-grow-1 text-center">{{ translate('Delivery_Information') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <?php
                if (session()->has('address')) {
                    $old = session()->get('address');
                } else {
                    $old = null;
                }
                ?>
                <form id='delivery_address_store'>
                    @csrf

                    <div class="row g-2" id="delivery_address">
                        <div class="col-md-6">
                            <label for="contact_person_name" class="input-label"
                                for="">{{ translate('messages.contact_person_name') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input id="contact_person_name" type="text" class="form-control"
                                name="contact_person_name" value="{{ $old ? $old['contact_person_name'] : '' }}"
                                placeholder="{{ translate('Ex: Jhone') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="contact_person_number" class="input-label"
                                for="">{{ translate('Contact Number') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input id="contact_person_number" type="tel" class="form-control"
                                name="contact_person_number" value="{{ $old ? $old['contact_person_number'] : '' }}"
                                placeholder="{{ translate('Ex: +3264124565') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="road" class="input-label"
                                for="">{{ translate('messages.Road') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input id="road" type="text" class="form-control" name="road"
                                value="{{ $old ? $old['road'] : '' }}" placeholder="{{ translate('Ex: 4th') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="house" class="input-label"
                                for="">{{ translate('messages.House') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input id="house" type="text" class="form-control" name="house"
                                value="{{ $old ? $old['house'] : '' }}" placeholder="{{ translate('Ex: 45/C') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="floor" class="input-label"
                                for="">{{ translate('messages.Floor') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input id="floor" type="text" class="form-control" name="floor"
                                value="{{ $old ? $old['floor'] : '' }}" placeholder="{{ translate('Ex: 1A') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="longitude" class="input-label"
                                for="">{{ translate('messages.longitude') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input type="text" class="form-control" id="longitude" name="longitude"
                                value="{{ $old ? $old['longitude'] : '' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="latitude" class="input-label"
                                for="">{{ translate('messages.latitude') }}<span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input type="text" class="form-control" id="latitude" name="latitude"
                                value="{{ $old ? $old['latitude'] : '' }}" readonly>
                        </div>
                        <div class="col-md-12">
                            <label for="address" class="input-label"
                                for="">{{ translate('messages.address') }}</label>
                            <textarea id="address" name="address" class="form-control" cols="30" rows="3"
                                placeholder="{{ translate('Ex: address') }}">{{ $old ? $old['address'] : '' }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <span class="text-primary">
                                    {{ translate('* pin the address in the map to calculate delivery fee') }}
                                </span>
                                <div>
                                    <span>{{ translate('Delivery_fee') }} :</span>
                                    <input type="hidden" name="distance" id="distance">
                                    <input type="hidden" name="delivery_fee" id="delivery_fee"
                                        value="{{ $old ? $old['delivery_fee'] : '' }}">
                                    <strong>{{ $old ? $old['delivery_fee'] : 0 }}
                                        {{ Helpers::currency_symbol() }}</strong>
                                </div>
                            </div>
                            <input id="pac-input" class="controls rounded initial-8"
                                title="{{ translate('messages.search_your_location_here') }}" type="text"
                                placeholder="{{ translate('messages.search_here') }}" />
                            <div class="mb-2 h-200px" id="map"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="btn--container justify-content-end">
                            <button class="btn btn-sm btn--primary w-100 delivery-Address-Store" type="button">
                                {{ translate('Update_Delivery address') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
