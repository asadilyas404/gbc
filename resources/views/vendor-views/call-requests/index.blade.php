@extends('layouts.vendor.app')

@section('title', 'Call requests')

@push('css_or_js')
    <style>
        .call-requests-table {
            color: #1e2022;
        }

        .call-requests-table thead th {
            color: #677788;
            font-weight: 600;
            border-bottom: 1px solid #e7eaf3;
        }

        .call-requests-table tbody td {
            color: #1e2022;
            vertical-align: middle;
            border-top: 1px solid #edf2f9;
        }

        .call-requests-table tbody tr:hover td {
            background: #f8fafc;
        }

        a.call-request-order-link {
            color: #377dff;
            font-weight: 700;
            text-decoration: underline;
        }

        a.call-request-order-link:hover {
            color: #1d4ed8;
        }

        .call-request-modal .modal-content {
            color: #1e2022;
        }

        .call-request-modal .modal-title {
            color: #132144;
            font-weight: 700;
        }

        .call-request-modal .cr-phone {
            color: #334155;
            font-size: 0.95rem;
        }

        .call-request-modal .cr-panel {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #fff;
            padding: 1rem;
            height: 100%;
        }

        .call-request-modal .cr-label {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .call-request-modal .cr-value {
            color: #0f172a;
            font-weight: 600;
        }

        .call-request-modal .cr-secondary {
            color: #334155;
        }

        .call-request-modal .cr-meta {
            color: #64748b;
            font-size: 0.875rem;
        }

        .call-request-modal .cr-eta {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e2e8f0;
            color: #0f172a;
            font-weight: 700;
        }

        .call-request-modal .cr-eta span {
            color: #2563eb;
        }

        .call-request-modal .cr-amount-row {
            color: #334155;
        }

        .call-request-modal .cr-amount-total {
            color: #0f172a;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .call-request-modal .cr-items {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .call-request-modal .cr-items-head {
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .call-request-modal .cr-item {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            color: #0f172a;
            border-bottom: 1px solid #edf2f9;
        }

        .call-request-modal .cr-item:last-child {
            border-bottom: 0;
        }

        .call-request-modal .cr-item-name {
            font-weight: 600;
        }

        .call-request-modal .cr-item-extra {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 0.15rem;
        }

        .call-request-modal .cr-item-price {
            font-weight: 700;
            white-space: nowrap;
        }

        .call-request-modal .modal-footer {
            border-top: 1px solid #e2e8f0;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">Call requests</h1>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs page-header-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}"
                    href="{{ route('vendor.call-requests.index', ['status' => 'pending']) }}">
                    Pending
                    <span class="badge badge-soft-info badge-pill ml-1">{{ $counts['pending'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'accepted' ? 'active' : '' }}"
                    href="{{ route('vendor.call-requests.index', ['status' => 'accepted']) }}">
                    Accepted
                    <span class="badge badge-soft-success badge-pill ml-1">{{ $counts['accepted'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}"
                    href="{{ route('vendor.call-requests.index', ['status' => 'rejected']) }}">
                    Rejected
                    <span class="badge badge-soft-danger badge-pill ml-1">{{ $counts['rejected'] }}</span>
                </a>
            </li>
        </ul>

        <div class="card">
            <div class="card-body p-0">
                @if ($requests->isEmpty())
                    <p class="mb-0 p-4" style="color:#334155;">No {{ $status }} requests for this branch.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0 call-requests-table">
                            <thead>
                                <tr class="small">
                                    <th>#</th>
                                    @if ($status === 'accepted')
                                        <th>Order</th>
                                    @endif
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Order type</th>
                                    <th>Zone</th>
                                    <th>Quoted time</th>
                                    <th class="text-right">{{ translate('messages.delivery_fee') }}</th>
                                    <th class="text-right">Items</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requests as $item)
                                    <tr style="cursor:pointer" data-toggle="modal"
                                        data-target="#call-request-modal-{{ $item->id }}">
                                        <td>{{ $item->id }}</td>
                                        @if ($status === 'accepted')
                                            <td onclick="event.stopPropagation();">
                                                @if ($item->order_id)
                                                    <a href="{{ route('vendor.order.details', ['id' => $item->order_id]) }}"
                                                        class="call-request-order-link">#{{ optional($item->order)->order_serial ?: $item->order_id }}</a>
                                                @endif
                                            </td>
                                        @endif
                                        <td>{{ optional($item->created_at)->format('H:i') }}</td>
                                        <td class="font-weight-bold">{{ $item->customer_name }}</td>
                                        <td>{{ $item->customer_phone }}</td>
                                        <td>
                                            @if ($item->delivery_type === 'take_away')
                                                {{ translate('Take Away') }}
                                            @elseif ($item->delivery_type === 'dine_in')
                                                {{ translate('Dine In') }}
                                            @elseif ($item->delivery_type === 'delivery')
                                                {{ translate('Delivery') }}
                                            @endif
                                        </td>
                                        <td>{{ $item->zone_name }}</td>
                                        <td>{{ (int) $item->customer_eta_minutes }} min</td>
                                        <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($item->delivery_fee) }}</td>
                                        <td class="text-right">{{ $item->details->count() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @foreach ($requests as $item)
        @php
            $collect = $item->details->sum(function ($detail) {
                return $detail->lineAmount();
            }) + (float) $item->delivery_fee;
        @endphp
        <div class="modal fade call-request-modal" id="call-request-modal-{{ $item->id }}" tabindex="-1" role="dialog"
            aria-labelledby="call-request-label-{{ $item->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1" id="call-request-label-{{ $item->id }}">
                                #{{ $item->id }} · {{ $item->customer_name }}
                            </h5>
                            <div class="cr-phone">{{ $item->customer_phone }}</div>
                        </div>
                        <div class="d-flex align-items-start">
                            @if ($item->status === 'pending')
                                <span class="badge badge-soft-info mr-2">Pending</span>
                            @elseif ($item->status === 'accepted')
                                <span class="badge badge-soft-success mr-2">Accepted</span>
                            @elseif ($item->status === 'rejected')
                                <span class="badge badge-soft-danger mr-2">Rejected</span>
                            @endif
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body">
                        @if ($item->status === 'rejected' && $item->reject_reason)
                            <div class="alert alert-soft-danger">Reject reason: {{ $item->reject_reason }}</div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="cr-panel">
                                    <div class="cr-label">Order details</div>
                                    <div class="cr-value mb-2">
                                        @if ($item->delivery_type === 'take_away')
                                            {{ translate('Take Away') }}
                                        @elseif ($item->delivery_type === 'dine_in')
                                            {{ translate('Dine In') }}
                                        @elseif ($item->delivery_type === 'delivery')
                                            {{ translate('Delivery') }}
                                        @endif
                                    </div>
                                    <div class="cr-secondary">{{ $item->address }}</div>
                                    @if ($item->latitude && $item->longitude)
                                        <div class="cr-meta mt-1">
                                            Map pin: {{ $item->latitude }}, {{ $item->longitude }}
                                        </div>
                                    @endif
                                    <div class="cr-secondary mt-2">{{ optional($item->branch)->branch_name }}</div>
                                    <div class="cr-meta">Zone: {{ $item->zone_name }}</div>
                                    <div class="cr-eta">
                                        Quoted time: <span>{{ (int) $item->customer_eta_minutes }} minutes</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="cr-panel">
                                    <div class="cr-label">Payment</div>
                                    <div class="d-flex justify-content-between cr-amount-row">
                                        <span>{{ translate('messages.delivery_fee') }}</span>
                                        <span>{{ \App\CentralLogics\Helpers::format_currency($item->delivery_fee) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 cr-amount-total">
                                        <span>Amount to collect</span>
                                        <span>{{ \App\CentralLogics\Helpers::format_currency($collect) }}</span>
                                    </div>
                                    @if ($item->accepted_at)
                                        <div class="cr-meta mt-3">Accepted at {{ $item->accepted_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                    @if ($item->order_id)
                                        <div class="cr-meta mt-1">
                                            <a href="{{ route('vendor.order.details', ['id' => $item->order_id]) }}"
                                                class="call-request-order-link">Order #{{ optional($item->order)->order_serial ?: $item->order_id }}</a>
                                        </div>
                                    @endif
                                    @if ($item->rejected_at)
                                        <div class="cr-meta mt-3">Rejected at {{ $item->rejected_at->format('Y-m-d H:i') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="cr-items mb-3">
                            <div class="cr-items-head">Items</div>
                            @forelse ($item->details as $detail)
                                <div class="cr-item">
                                    <div>
                                        <div class="cr-item-name">{{ $detail->food_name }} × {{ (int) $detail->quantity }}</div>
                                        @if ($detail->summaryText())
                                            <div class="cr-item-extra">{{ $detail->summaryText() }}</div>
                                        @endif
                                        @if ($detail->notes)
                                            <div class="cr-item-extra">{{ $detail->notes }}</div>
                                        @endif
                                    </div>
                                    <div class="cr-item-price">{{ \App\CentralLogics\Helpers::format_currency($detail->lineAmount()) }}</div>
                                </div>
                            @empty
                                <p class="cr-meta mb-0 p-3">No items.</p>
                            @endforelse
                        </div>

                        @if ($item->status === 'pending')
                            <div class="call-request-accept-box d-none" id="accept-box-{{ $item->id }}">
                                <div class="cr-value mb-2">Accept this request?</div>
                                <p class="cr-secondary mb-3 mb-0">This will create an unpaid order and send it to the kitchen.</p>
                                <div class="text-right">
                                    <button type="button" class="btn btn-white call-request-accept-cancel"
                                        data-id="{{ $item->id }}">Cancel</button>
                                    <form action="{{ route('vendor.call-requests.accept', $item->id) }}" method="post"
                                        class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn--primary">Confirm accept</button>
                                    </form>
                                </div>
                            </div>

                            <div class="call-request-reject-box d-none" id="reject-box-{{ $item->id }}">
                                <form action="{{ route('vendor.call-requests.reject', $item->id) }}" method="post">
                                    @csrf
                                    <input type="hidden" name="open_id" value="{{ $item->id }}">
                                    <label class="cr-value" for="reject_reason_{{ $item->id }}">Reject reason</label>
                                    <textarea name="reject_reason" id="reject_reason_{{ $item->id }}" class="form-control" rows="3"
                                        maxlength="500" required>{{ (string) old('open_id') === (string) $item->id ? old('reject_reason') : '' }}</textarea>
                                    @if ($errors->has('reject_reason') && (string) old('open_id') === (string) $item->id)
                                        <div class="text-danger small mt-1">{{ $errors->first('reject_reason') }}</div>
                                    @endif
                                    <div class="mt-2 text-right">
                                        <button type="button" class="btn btn-white call-request-reject-cancel"
                                            data-id="{{ $item->id }}">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Reject</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                    @if ($item->status === 'pending')
                        <div class="modal-footer call-request-actions" id="actions-{{ $item->id }}">
                            <button type="button" class="btn btn-outline-danger call-request-reject-open"
                                data-id="{{ $item->id }}">Reject</button>
                            <button type="button" class="btn btn--primary call-request-accept-open"
                                data-id="{{ $item->id }}">Accept</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('script_2')
    <script>
        $(function () {
            function showActionBox(id, box) {
                $('#actions-' + id).addClass('d-none');
                $('#accept-box-' + id).addClass('d-none');
                $('#reject-box-' + id).addClass('d-none');
                $('#' + box + '-' + id).removeClass('d-none');
            }

            function showActions(id) {
                $('#accept-box-' + id).addClass('d-none');
                $('#reject-box-' + id).addClass('d-none');
                $('#actions-' + id).removeClass('d-none');
            }

            $('.call-request-accept-open').on('click', function () {
                showActionBox($(this).data('id'), 'accept-box');
            });

            $('.call-request-accept-cancel').on('click', function () {
                showActions($(this).data('id'));
            });

            $('.call-request-reject-open').on('click', function () {
                showActionBox($(this).data('id'), 'reject-box');
            });

            $('.call-request-reject-cancel').on('click', function () {
                showActions($(this).data('id'));
            });

            @if ($errors->has('reject_reason') && old('open_id'))
                var openId = @json((string) old('open_id'));
                $('#call-request-modal-' + openId).modal('show');
                $('#actions-' + openId).addClass('d-none');
                $('#reject-box-' + openId).removeClass('d-none');
            @elseif (request()->query('open'))
                $('#call-request-modal-{{ request()->query('open') }}').modal('show');
            @endif
        });
    </script>
@endpush
