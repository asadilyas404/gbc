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
            <form id="printer-settings-form">
                @csrf
                <div class="row">
                    <div class="col-md-7">
                        <label for="billPrinter">Bill Printer</label>
                        <input type="text" id="billPrinter" name="billPrinter" class="form-control" placeholder="Enter bill printer name">
                    </div>

                    <div class="col-md-7 mt-3">
                        <label for="kitchenPrinter">Kitchen Printer</label>
                        <input type="text" id="kitchenPrinter" name="kitchenPrinter" class="form-control" placeholder="Enter kitchen printer name">
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
        document.addEventListener('DOMContentLoaded', function() {
            loadSavedPrinters();

            function loadSavedPrinters() {
                fetch("{{ route('vendor.printer.settings') }}")
                    .then(res => res.json())
                    .then(data => {
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
