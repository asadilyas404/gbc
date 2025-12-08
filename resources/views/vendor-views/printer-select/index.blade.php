@extends('layouts.vendor.app')

@section('title', 'Settings')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>

    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-add-circle-outlined"></i> Settings
                </h1>
            </div>
            <div class="my-2">
                <div class="row g-2 align-items-center justify-content-end">
                    @if (app()->environment('local'))
                        <div class="col-auto">
                            <button type="button" id="print-canceled-items-btn" class="btn max-sm-12 btn--warning w-100">
                                Print Canceled Items
                            </button>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('vendor.settings.sync.users') }}" class="btn max-sm-12 btn--primary w-100">
                                Sync Users
                            </a>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('vendor.settings.sync.customers') }}" class="btn max-sm-12 btn--primary w-100">
                                Sync Customers
                            </a>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('vendor.settings.sync.branches.restaurants') }}" class="btn max-sm-12 btn--warning w-100">
                                Sync Branches & Restaurants
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="container mt-4">
            <form id="printer-settings-form">
                @csrf
                <div class="row">
                    <div class="col-md-7">
                        <label for="ordersDate">Order Date</label>
                        <input type="date" id="ordersDate" name="ordersDate" class="form-control">
                    </div>

                    <div class="col-md-7">
                        <label for="billPrinter">Bill Printer</label>
                        <input type="text" id="billPrinter" name="billPrinter" class="form-control"
                            placeholder="Enter bill printer name">
                    </div>

                    <div class="col-md-7 mt-3">
                        <label for="kitchenPrinter">Kitchen Printer</label>
                        <input type="text" id="kitchenPrinter" name="kitchenPrinter" class="form-control"
                            placeholder="Enter kitchen printer name">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-5">Save Printers</button>
            </form>

        </div>
    </div>
@endsection

@push('script')
@endpush

@push('script_2')
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let today = new Date();
            let tomorrow = new Date();
            tomorrow.setDate(today.getDate() + 1); // only 1 day ahead

            // Format to YYYY-MM-DD
            let format = (date) => date.toISOString().split('T')[0];

            document.getElementById("ordersDate").setAttribute("min", format(today));
            document.getElementById("ordersDate").setAttribute("max", format(tomorrow));
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            loadSavedPrinters();

            function loadSavedPrinters() {
                fetch("{{ route('vendor.printer.settings') }}")
                    .then(res => res.json())
                    .then(data => {
                        if (data.orders_date) {
                            const dateValue = data.orders_date.split(' ')[0];
                            document.getElementById('ordersDate').value = dateValue;
                        }
                        if (data.bill_printer) {
                            document.getElementById('billPrinter').value = data.bill_printer;
                        }
                        if (data.kitchen_printer) {
                            document.getElementById('kitchenPrinter').value = data.kitchen_printer;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading saved printers:', error);
                    });
            }

            let printCanceledItemsBtn = document.getElementById('print-canceled-items-btn');
            if (printCanceledItemsBtn) {
                printCanceledItemsBtn.addEventListener('click', function() {
                    swal.fire({
                        title: 'Enter Order Date',
                        html: `<input type="date" id="swal-order-date" class="swal2-input" placeholder="Select date">`,
                        showCancelButton: true,
                        confirmButtonText: 'Print',
                        preConfirm: () => {
                            const orderDate = document.getElementById('swal-order-date').value;
                            if (!orderDate) {
                                swal.showValidationMessage('Please select a date');
                            }
                            return orderDate;
                        }
                    }).then((result) => {
                        if (result.value) {
                            const orderDate = result.value;
                            $('#loading').show();

                            fetch("{{ route('vendor.order.canceled.items') }}", {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        order_date: orderDate
                                    })
                                })
                                .then(res => res.json())
                                .then(data => {
                                    $('#loading').hide();
                                    if (data.success) {
                                        alert('Canceled items printed successfully!');
                                    } else {
                                        alert(data.message || 'Failed to print canceled items.');
                                    }
                                })
                                .catch(error => {
                                    console.error(error);
                                    alert('Error occurred while printing canceled items.');
                                });
                        }
                    });
                });
            }

            document.getElementById('printer-settings-form').addEventListener('submit', function(e) {
                e.preventDefault();
                $('#loading').show();
                const formData = new FormData(this);

                fetch("{{ route('vendor.printer.settings.save') }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': formData.get('_token')
                        },
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        $('#loading').hide();
                        if (data.success) {
                            alert('Settings saved successfully!');
                        } else {
                            alert(data.message || 'Failed to save settings.');
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        alert('Error occurred while saving settings.');
                    });
            });
        });
    </script>
@endpush
