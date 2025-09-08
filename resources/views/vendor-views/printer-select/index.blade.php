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
            <h1 class="page-header-title">
                <i class="tio-add-circle-outlined"></i> Settings
            </h1>
        </div>

        <div class="container mt-4">

            <!-- Success/Error messages -->
            <div id="message-container" class="mt-3" style="display: none;">
                <div id="message" class="alert" role="alert"></div>
            </div>

            <form id="printer-settings-form">
                @csrf
                <div class="row">
                    <div class="col-md-7">
                        <label for="ordersDate">Order Date</label>
                        <input type="date" id="ordersDate" name="ordersDate" class="form-control">
                    </div>

                    <div class="col-md-7">
                        <label for="billPrinter">Bill Printer</label>
                        <input type="text" id="billPrinter" name="billPrinter" class="form-control" placeholder="Enter bill printer name">
                    </div>

                    <div class="col-md-7 mt-3">
                        <label for="kitchenPrinter">Kitchen Printer</label>
                        <input type="text" id="kitchenPrinter" name="kitchenPrinter" class="form-control" placeholder="Enter kitchen printer name">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-5" id="save-btn">
                    <span id="save-text">Save Printers</span>
                    <span id="save-spinner" class="spinner-border spinner-border-sm d-none" role="status">
                        <span class="sr-only">Loading...</span>
                    </span>
                </button>
            </form>

        </div>
    </div>
@endsection

@push('script')
@endpush

@push('script_2')
    <script>
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

            document.getElementById('printer-settings-form').addEventListener('submit', function(e) {
                e.preventDefault();

                // Show loading state
                document.getElementById('loading').style.display = 'block';
                document.getElementById('save-btn').disabled = true;
                document.getElementById('save-text').textContent = 'Saving...';
                document.getElementById('save-spinner').classList.remove('d-none');
                document.getElementById('message-container').style.display = 'none';

                const formData = new FormData(this);
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                fetch("{{ route('vendor.printer.settings.save') }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(formData)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Hide loading state
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('save-btn').disabled = false;
                        document.getElementById('save-text').textContent = 'Save Printers';
                        document.getElementById('save-spinner').classList.add('d-none');

                        // Show message
                        const messageContainer = document.getElementById('message-container');
                        const message = document.getElementById('message');

                        if (data.success) {
                            message.className = 'alert alert-success';
                            message.textContent = 'Settings saved successfully!';
                        } else {
                            message.className = 'alert alert-danger';
                            message.textContent = data.message || 'Failed to save settings.';
                        }
                        messageContainer.style.display = 'block';

                        // Auto-hide success message after 3 seconds
                        if (data.success) {
                            setTimeout(() => {
                                messageContainer.style.display = 'none';
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);

                        // Hide loading state
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('save-btn').disabled = false;
                        document.getElementById('save-text').textContent = 'Save Printers';
                        document.getElementById('save-spinner').classList.add('d-none');

                        // Show error message
                        const messageContainer = document.getElementById('message-container');
                        const message = document.getElementById('message');
                        message.className = 'alert alert-danger';
                        message.textContent = 'Error occurred while saving settings. Please try again.';
                        messageContainer.style.display = 'block';
                    });
            });
        });
    </script>
@endpush
