@extends('layouts.vendor.app')

@section('title', 'Shift Session Verification')

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
                <i class="tio-checkmark-outlined"></i> Shift Session Verification Requests
            </h1>
        </div>
        <!-- End Page Header -->

       <div class="table-responsive datatable-custom">
            <table id="datatable"
                class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                <thead class="thead-light">
                    <tr>
                        <th class="w-140px">{{ translate('messages.request_by') }}</th>
                        <th class="w-140px">{{ translate('messages.request_date') }}</th>
                        <th class="w-140px">{{ translate('messages.cash_amount') }}</th>
                        <th class="w-100px">{{ translate('messages.visa_amount') }}</th>
                        <th class="w-100px">{{ translate('messages.total_amount') }}</th>
                        <th class="w-100px text-center">{{ translate('messages.actions') }}</th>
                    </tr>
                </thead>

                <tbody id="set-rows">
                    @foreach ($list as $key => $request)
                        <tr data-id="{{ $request->session_id }}">
                            <td>{{ $request->user->name ?? '-'}}</td>
                            <td>{{ $request->end_date }}</td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($request->closing_cash) }}</td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($request->closing_visa) }}</td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($request->closing_cash + $request->closing_visa) }}</td>
                            <td class="text-center">
                                @if($request->verified == 0)
                                    <button class="btn btn-sm btn-outline-success" id="approve-session">
                                        Approve
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" id="reject-session">
                                        Reject
                                    </button>
                                @elseif ($request->verified = 1)
                                    <button class="btn btn-sm btn-success">
                                        Approved
                                    </button>
                                @elseif ($request->verified = 2)
                                    <button class="btn btn-sm btn-danger">
                                        Rejected
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
       </div>
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

            $(document).on('click', '#approve-session, #reject-session', function(e){
                e.preventDefault();
                
                let button = $(this);
                let tr = button.closest('tr');
                let sessionId = tr.data('id'); // assign data-id="{{ $request->id }}" to <tr>
                let action = button.attr('id') === 'approve-session' ? 'approve' : 'reject';

                let url = action === 'approve'
                ? '{{ route("vendor.shift-session.approve") }}'
                : '{{ route("vendor.shift-session.reject") }}';

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        id: sessionId,
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function(){
                        button.prop('disabled', true)
                        .html('<span class="spinner-border spinner-border-sm me-1"></span>');
                    },
                    success: function(response){
                        toastr.success(response.message);
                        tr.animate(
                            { opacity: 0, height: 0 },
                            300,
                            function () {
                                $(this).remove();
                            }
                        );
                    },
                    error: function(xhr) {
                        let message = xhr.responseJSON?.message ?? 'Something went wrong';
                        toastr.error(message);
                    },
                    complete: function(){
                        button.prop('disabled', false).text(
                            button.attr('id') === 'approve-session' ? 'Approve' : 'Reject'
                        );
                    }
                });
            });
        });
    </script>
@endpush
