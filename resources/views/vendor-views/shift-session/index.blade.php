@extends('layouts.vendor.app')

@section('title', 'Shift Session Management')

@push('css_or_js')
<style>
    .session-card {
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    .session-status-open {
        color: #1cc88a;
        font-weight: 600;
    }
    .session-status-closed {
        color: #e74a3b;
        font-weight: 600;
    }
    .shift-info {
        background-color: #f8f9fc;
        border-radius: 0.35rem;
        padding: 1rem;
    }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-time"></i> Shift Session Management
            </h1>
        </div>
        <!-- End Page Header -->

        @if($currentSession)
            <!-- Current Session Card -->
            <div class="card session-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="tio-check-circle text-success"></i> Current Active Session
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="shift-info">
                                <h6 class="text-primary">Session Details</h6>
                                <p class="mb-1"><strong>Session #:</strong> {{ $currentSession->session_no }}</p>
                                <p class="mb-1"><strong>Shift:</strong> {{ $currentSession->shift_name }}</p>
                                <p class="mb-1"><strong>Started:</strong> {{ $currentSession->start_date->format('M d, Y H:i:s') }}</p>
                                <p class="mb-0"><strong>Status:</strong>
                                    <span class="session-status-open">{{ ucfirst($currentSession->session_status) }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="shift-info">
                                <h6 class="text-primary">Opening Amounts</h6>
                                <p class="mb-1"><strong>Opening Cash:</strong> {{ \App\CentralLogics\Helpers::format_currency($currentSession->opening_cash) }}</p>
                                <p class="mb-0"><strong>Opening Visa:</strong> {{ \App\CentralLogics\Helpers::format_currency($currentSession->opening_visa) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Close Session Form -->
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Close Session</h6>
                            <form action="{{ route('vendor.shift-session.close') }}" method="post" id="closeSessionForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Closing Cash Amount</label>
                                            <input type="number" name="closing_cash" class="form-control"
                                                   step="0.01" min="0" required
                                                   placeholder="Enter closing cash amount">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Closing Visa Amount</label>
                                            <input type="number" name="closing_visa" class="form-control"
                                                   step="0.01" min="0" required
                                                   placeholder="Enter closing visa amount">
                                        </div>
                                    </div>
                                </div>
                                <div class="btn--container justify-content-end">
                                    <button type="submit" class="btn btn--danger"
                                            onclick="return confirm('Are you sure you want to close this shift session?')">
                                        <i class="tio-close"></i> Close Session
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Start New Session Card -->
            <div class="card session-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="tio-play-circle text-primary"></i> Start New Shift Session
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('vendor.shift-session.store') }}" method="post" id="startSessionForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Select Shift <span class="text-danger">*</span></label>
                                    <select name="shift_id" class="form-control js-select2-custom" required>
                                        <option value="">-- Select Shift --</option>
                                        @foreach($shifts as $shift)
                                            <option value="{{ $shift->shift_id }}">{{ $shift->shift_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Opening Cash Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="opening_cash" class="form-control"
                                           step="0.01" min="0" required
                                           placeholder="Enter opening cash amount">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Opening Visa Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="opening_visa" class="form-control"
                                           step="0.01" min="0" required
                                           placeholder="Enter opening visa amount">
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end">
                            <button type="submit" class="btn btn--primary">
                                <i class="tio-play"></i> Start Session
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Session History (Optional - if you want to show recent sessions) -->
        {{-- @if(!$currentSession)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="tio-history"></i> Recent Sessions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center text-muted">
                        <i class="tio-inbox tio-2x mb-3"></i>
                        <p>No recent sessions found. Start a new shift session to begin tracking.</p>
                    </div>
                </div>
            </div>
        @endif --}}
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $(document).on('ready', function () {
            // Initialize Select2
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });

            // Form validation
            $('#startSessionForm').on('submit', function(e) {
                const shiftId = $('select[name="shift_id"]').val();
                const openingCash = $('input[name="opening_cash"]').val();
                const openingVisa = $('input[name="opening_visa"]').val();

                if (!shiftId || !openingCash || !openingVisa) {
                    e.preventDefault();
                    toastr.error('Please fill in all required fields.');
                    return false;
                }

                if (parseFloat(openingCash) < 0 || parseFloat(openingVisa) < 0) {
                    e.preventDefault();
                    toastr.error('Amounts cannot be negative.');
                    return false;
                }
            });

            $('#closeSessionForm').on('submit', function(e) {
                const closingCash = $('input[name="closing_cash"]').val();
                const closingVisa = $('input[name="closing_visa"]').val();

                if (!closingCash || !closingVisa) {
                    e.preventDefault();
                    toastr.error('Please fill in all required fields.');
                    return false;
                }

                if (parseFloat(closingCash) < 0 || parseFloat(closingVisa) < 0) {
                    e.preventDefault();
                    toastr.error('Amounts cannot be negative.');
                    return false;
                }
            });
        });
    </script>
@endpush
