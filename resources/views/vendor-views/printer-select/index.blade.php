@extends('layouts.vendor.app')

@section('title', 'Select Printers')

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
                <i class="tio-add-circle-outlined"></i> Select Printers
            </h1>
        </div>

        <div class="container mt-4">
            <!-- Printer Settings Form -->
            <form id="printer-settings-form">
                @csrf
                <div class="row">
                    <div class="col-md-7">
                        <label for="billPrinter">Bill Printer</label>
                        <select id="billPrinter" name="billPrinter" class="form-control">
                            <option value="">-- Select Bill Printer --</option>
                        </select>
                    </div>

                    <div class="col-md-7 mt-3">
                        <label for="kitchenPrinter">Kitchen Printer</label>
                        <select id="kitchenPrinter" name="kitchenPrinter" class="form-control">
                            <option value="">-- Select Kitchen Printer --</option>
                        </select>
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
    <script src="{{ dynamicAsset('public/assets/restaurant_panel/qz-tray.js') }}"></script>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Connect QZ Tray first
    qz.websocket.connect().then(() => {
        loadPrinters();
    }).catch(err => {
        alert('QZ Tray connection failed: ' + err);
    });

    // Load available printers and preselect saved ones
    function loadPrinters() {
        qz.printers.find().then(function(printers) {
            const billSelect = document.getElementById('billPrinter');
            const kitchenSelect = document.getElementById('kitchenPrinter');

            // Clear previous options
            billSelect.innerHTML = `<option value="">-- Select Bill Printer --</option>`;
            kitchenSelect.innerHTML = `<option value="">-- Select Kitchen Printer --</option>`;

            // Populate options
            printers.forEach(function (printer) {
                billSelect.innerHTML += `<option value="${printer}">${printer}</option>`;
                kitchenSelect.innerHTML += `<option value="${printer}">${printer}</option>`;
            });

            // Load already saved printer values via AJAX
            fetch("{{ route('vendor.printer.settings') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.bill_print) {
                        billSelect.value = data.bill_print;
                    }
                    if (data.kitchen_print) {
                        kitchenSelect.value = data.kitchen_print;
                    }
                });
        });
    }

    // Handle form submit
    document.getElementById('printer-settings-form').addEventListener('submit', function (e) {
        e.preventDefault();
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
            if (data.success) {
                alert('Printers saved successfully!');
            } else {
                alert('Failed to save printers.');
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error occurred while saving printers.');
        });
    });
});
</script>

@endpush
