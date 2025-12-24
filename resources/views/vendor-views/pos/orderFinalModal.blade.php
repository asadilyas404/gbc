@php
    use App\CentralLogics\Helpers;
@endphp

<style>
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
</style>

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
                                <h5 class="text-white">{{ translate('Invoice Amount') }}</h5>
                                <h4 id="invoice_amount" class="font-weight-bold">
                                    <span class="text-white"></span>
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="text-white">{{ translate('Cash Paid') }}</h5>
                                <h4 id="cash_paid_display" class="font-weight-bold text-white">
                                    {{ Helpers::format_currency(0.0) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5 class="text-white">{{ translate('Cash Return') }}</h5>
                                <h4 id="cash_return" class="font-weight-bold text-white">
                                    {{ Helpers::format_currency(0.0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('vendor.pos.order') }}" id='order_place' method="post">
                    @csrf
                    <input type="hidden" name="user_id" id="customer_id">
                    <input type="hidden" name="partner_id" id="partner_id" value="{{ $partner_id ?? '' }}">
                    <!-- Customer Details Section -->
                    <div class="row pl-2 mt-3">
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="customer_name" class="input-label">
                                    {{ translate('Customer Name') }}
                                </label>
                                <input id="customer_name" type="text" name="customer_name" class="form-control"
                                    value="" placeholder="{{ translate('Customer Name') }}">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="car_number" class="input-label">{{ translate('Car Number') }}</label>
                                <input id="car_number" type="text" name="car_number" class="form-control"
                                    value="" placeholder="{{ translate('Car Number') }}">
                            </div>
                        </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-group">
                            <label for="phone" class="input-label">
                                {{ translate('Phone') }}
                            </label>
                            <input id="phone" type="tel" name="phone" class="form-control" value=""
                                placeholder="{{ translate('Phone') }}">
                        </div>
                    </div>
                    </div>
                    <input type="hidden" name="invoice_amount" id="invoice_amount_input" value="">

                    <!-- Payment Details Section -->
                    <div class="row pl-2">
                        <div class="col-lg-8">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-1">
                                    <label for="payment_type_cash" class="form-group bg-light d-flex align-items-center gap-2 m-0 payment-selection-box">
                                        <input type="radio" id="payment_type_cash" class="payment_type" name="select_payment_type" value="cash_payment"
                                            >
                                        <span class="input-label m-0">
                                            {{ translate('Cash') }}
                                        </span>
                                    </label>
                                </div>        
                                <div class="col-md-6 mb-1">
                                    <label for="payment_type_card" class="form-group bg-light d-flex align-items-center gap-2 m-0 payment-selection-box">
                                        <input type="radio" id="payment_type_card" class="payment_type" name="select_payment_type" value="card_payment"
                                            >
                                        <span class="input-label m-0">
                                            {{ translate('Card') }}
                                        </span>
                                    </label>
                                </div>        
                                <div class="col-md-6 mb-1">
                                    <label for="payment_type_both" class="form-group bg-light d-flex align-items-center gap-2 m-0 payment-selection-box">
                                        <input type="radio" id="payment_type_both" class="payment_type" name="select_payment_type" value="both_payment"
                                            >
                                        <span class="input-label m-0">
                                            {{ translate('Cash & Card') }}
                                        </span>
                                    </label>
                                </div>    
                                 <div class="col-md-6 mb-1">
                                    <label for="payment_type_credit" class="form-group bg-light d-flex align-items-center gap-2 m-0 payment-selection-box">
                                        <input type="radio" id="payment_type_credit" class="payment_type" name="select_payment_type" value="credit_payment"
                                            >
                                        <span class="input-label m-0">
                                            Credit
                                        </span>
                                    </label>
                                </div>     
                            </div>
                            <div class="row">
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="cash_paid"
                                            class="input-label">{{ translate('Cash Amount') }}</label>
                                        <input id="cash_paid" type="text" name="cash_paid" class="form-control"
                                            onfocus="this.select();"
                                            min="0" step="0.001"
                                            placeholder="{{ translate('Enter cash amount') }}" value="">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="delivery_type" class="input-label">Order Type</label>
                                        <select id="delivery_type" name="delivery_type" class="form-control">
                                        <option value="take_away">
                                                Take away</option>
                                            <option value="dine_in">
                                                Dine In</option>
                                            <option value="delivery">
                                                Delivery</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="form-group">
                                        <label for="card_paid"
                                            class="input-label">{{ translate('Card Amount') }}</label>
                                        <input id="card_paid" type="text" name="card_paid" class="form-control"
                                            onfocus="this.select();"
                                            min="0" step="0.001"
                                            placeholder="{{ translate('Enter card amount') }}" value="">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="bank_account"
                                            class="input-label">{{ translate('Select Account') }}</label>
                                        <select id="bank_account" name="bank_account" class="form-control">
                                            <option value="">
                                                {{ translate('Select Option') }}</option>  
                                            @php
                                                $bankaccounts = DB::table('tbl_defi_bank')->get();
                                            @endphp
                                            @foreach ($bankaccounts as $account)
                                                <option value="{{ $account->bank_account_id }}"
                                                @if(session()->has('bank_account') && session('bank_account') == $account->bank_account_id) selected @endif
                                                {{ old('bank_account', $draftDetails->bank_account ?? '') == $account->bank_account_id ? 'selected' : '' }}>
                                                {{ $account->bank_name }}</option>    
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="order_notes" class="input-label">{{ translate('Order Notes') }}</label>
                                    <input id="order_notes" type="text" name="order_notes" class="form-control"
                                        value="" placeholder="{{ translate('Order Notes') }}">
                                </div>
                            </div>
                            <input type="hidden" name="order_draft" id="order_draft" value="final">
                            <div class="col-12">
                                <!-- Submit Button -->
                                <div class="btn--container justify-content-end mt-4">
                                    <button type="button" class="btn btn-secondary"
                                        data-dismiss="modal">{{ translate('Close') }}</button>
                                    <button type="submit"
                                        class="btn btn--primary">{{ translate('Place Order') }}</button>
                                    <button type="submit" class="btn btn--warning"
                                        onclick="document.getElementById('order_draft').value='draft'">
                                        {{ translate('Unpaid Order') }}
                                    </button>
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
                </form>
            </div>
        </div>
    </div>
</div>
