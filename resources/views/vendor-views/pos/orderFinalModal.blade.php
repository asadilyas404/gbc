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
                                    <h5>{{ translate('Invoice Amount') }}</h5>
                                    <h4 id="invoice_amount" class="font-weight-bold">
                                        <span></span>
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
                                    value=""
                                    placeholder="{{ translate('Customer Name') }}">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="car_number" class="input-label">{{ translate('Car Number') }}</label>
                                <input id="car_number" type="text" name="car_number" class="form-control"
                                    value=""
                                    placeholder="{{ translate('Car Number') }}">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <label for="phone" class="input-label">
                                    {{ translate('Phone') }} ({{ translate('with_country_code') }})
                                </label>
                                <input id="phone" type="tel" name="phone" class="form-control"
                                    value=""
                                    placeholder="{{ translate('Phone') }}">
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
                                            placeholder="{{ translate('Enter cash amount') }}"
                                            value="">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="delivery_type" class="input-label">Delivery Type</label>
                                        <select id="delivery_type" name="delivery_type" class="form-control">
                                            <option value="dine_in">
                                                Dine In</option>
                                            <option value="take_away">
                                                Take away</option>
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
                                            min="0" step="0.001"
                                            placeholder="{{ translate('Enter card amount') }}"
                                            value="">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label for="bank_account"
                                            class="input-label">{{ translate('Select Account') }}</label>
                                        <select id="bank_account" name="bank_account" class="form-control">
                                            <option value="">{{ translate('Select an option') }}</option>
                                            <option value="1">
                                                {{ translate('Bank 1') }}</option>
                                            <option value="2">
                                                {{ translate('Bank 2') }}</option>
                                            <option value="3">
                                                {{ translate('Bank 3') }}</option>
                                        </select>
                                    </div>
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
                </div>
            </div>
        </div>
    </div>


    <script>

$(document).ready(function() {

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

    </script>
