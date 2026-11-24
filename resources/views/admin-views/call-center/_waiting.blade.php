<div class="cc-requests">
    <div id="cc-request-browser" class="d-flex flex-column h-100 min-h-0">
        <div class="cc-requests-toolbar">
            <ul class="nav nav-tabs" id="cc-request-tabs">
                <li class="nav-item">
                    <a class="nav-link cc-request-tab {{ $status === 'pending' ? 'active' : '' }}" href="#" data-status="pending">
                        Pending
                        <span class="badge badge-soft-info badge-pill ml-1">{{ $counts['pending'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link cc-request-tab {{ $status === 'accepted' ? 'active' : '' }}" href="#" data-status="accepted">
                        Accepted
                        <span class="text-muted small font-weight-normal">last 24 hours</span>
                        <span class="badge badge-soft-success badge-pill ml-1">{{ $counts['accepted'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link cc-request-tab {{ $status === 'rejected' ? 'active' : '' }}" href="#" data-status="rejected">
                        Rejected
                        <span class="badge badge-soft-danger badge-pill ml-1">{{ $counts['rejected'] }}</span>
                    </a>
                </li>
            </ul>
            <input type="search" id="cc-request-search" class="form-control" value="{{ $q }}" placeholder="Search name, phone, or branch" autocomplete="off">
        </div>

        <div class="cc-requests-scroll">
            @if ($waiting->isEmpty())
                <div class="cc-requests-empty">No {{ $status }} requests{{ $q !== '' ? ' match this search' : '' }}.</div>
            @else
                <table class="table table-hover table-borderless mb-0 cc-request-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Branch</th>
                            <th>Time</th>
                            <th class="text-right">To collect</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($waiting as $item)
                            @php
                                $collect = $item->details->sum(function ($detail) {
                                    return $detail->lineAmount();
                                }) + (float) $item->delivery_fee;
                            @endphp
                            <tr class="cc-request-row" data-id="{{ $item->id }}" data-status="{{ $item->status }}">
                                <td>{{ $item->id }}</td>
                                <td class="font-weight-bold text-truncate cc-request-name">{{ $item->customer_name }}</td>
                                <td>{{ $item->customer_phone }}</td>
                                <td class="text-truncate cc-request-branch">{{ optional($item->branch)->branch_name }}</td>
                                <td>{{ (int) $item->customer_eta_minutes }} min</td>
                                <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($collect) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="cc-requests-footer">
            <span class="text-muted small">
                @if ($waiting->total() === 0)
                    0 requests
                @else
                    {{ $waiting->firstItem() }}–{{ $waiting->lastItem() }} of {{ $waiting->total() }}
                @endif
            </span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-white cc-request-page" data-page="{{ $waiting->currentPage() - 1 }}" {{ $waiting->onFirstPage() ? 'disabled' : '' }}>Previous</button>
                <button type="button" class="btn btn-white" disabled>{{ $waiting->currentPage() }} / {{ max($waiting->lastPage(), 1) }}</button>
                <button type="button" class="btn btn-white cc-request-page" data-page="{{ $waiting->currentPage() + 1 }}" {{ $waiting->hasMorePages() ? '' : 'disabled' }}>Next</button>
            </div>
        </div>
    </div>

    @foreach ($waiting as $item)
        @php
            $collect = $item->details->sum(function ($detail) {
                return $detail->lineAmount();
            }) + (float) $item->delivery_fee;
        @endphp
        <div class="cc-request-detail d-none" data-id="{{ $item->id }}">
            <button type="button" class="btn btn-link px-0 mb-2 cc-request-back">&larr; Back to list</button>
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="mb-1">#{{ $item->id }} · {{ $item->customer_name }}</h5>
                    <div class="text-muted">{{ $item->customer_phone }}</div>
                </div>
                @if ($item->status === 'accepted')
                    <span class="badge badge-soft-info">Accepted</span>
                @elseif ($item->status === 'rejected')
                    <span class="badge badge-soft-danger">Rejected</span>
                @else
                    <span class="badge badge-soft-success">Pending</span>
                @endif
            </div>
            @if ($item->status === 'rejected' && $item->reject_reason)
                <div class="alert alert-soft-danger">Rejected: {{ $item->reject_reason }}</div>
            @endif
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-2">Delivery</div>
                        <div class="mb-2">
                            @if ($item->delivery_type === 'take_away')
                                {{ translate('Take Away') }}
                            @elseif ($item->delivery_type === 'dine_in')
                                {{ translate('Dine In') }}
                            @elseif ($item->delivery_type === 'delivery')
                                {{ translate('Delivery') }}
                            @endif
                        </div>
                        <div>{{ $item->address }}</div>
                        <div class="mt-2">{{ optional($item->branch)->branch_name }}</div>
                        <div class="text-muted">{{ $item->zone_name }}</div>
                        <div class="mt-2 font-weight-bold">Tell the customer: {{ (int) $item->customer_eta_minutes }} minutes</div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-2">Amount</div>
                        <div class="d-flex justify-content-between"><span>{{ translate('messages.delivery_fee') }}</span><span>{{ \App\CentralLogics\Helpers::format_currency($item->delivery_fee) }}</span></div>
                        <div class="d-flex justify-content-between mt-2 font-weight-bold"><span>To collect</span><span>{{ \App\CentralLogics\Helpers::format_currency($collect) }}</span></div>
                    </div>
                </div>
            </div>
            <div class="border rounded mb-3">
                <div class="px-3 py-2 border-bottom text-muted small">Items</div>
                @foreach ($item->details as $detail)
                    <div class="px-3 py-2 d-flex justify-content-between {{ $loop->last ? '' : 'border-bottom' }}">
                        <div>
                            {{ $detail->food_name }} × {{ (int) $detail->quantity }}
                            @if ($detail->summaryText())
                                <div class="text-muted small">{{ $detail->summaryText() }}</div>
                            @endif
                        </div>
                        <div>{{ \App\CentralLogics\Helpers::format_currency($detail->lineAmount()) }}</div>
                    </div>
                @endforeach
            </div>
            @if ($item->status !== 'accepted')
            <div class="border rounded p-3 mb-3">
                <div class="text-muted small mb-2">Move to another branch</div>
                <form action="{{ route($routePrefix . '.reassign', $item->id) }}" method="post" class="cc-move">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="col-md-4 mb-2">
                            <label class="mb-1">Branch</label>
                            <select name="restaurant_id" class="form-control cc-move-branch" required>
                                <option value="">Select branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->branch_id }}" {{ (string) $item->restaurant_id === (string) $branch->branch_id ? 'selected' : '' }}>
                                        {{ $branch->branch_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="mb-1">Zone</label>
                            <select name="zone_id" class="form-control cc-move-zone" required data-selected="{{ $item->zone_id }}"></select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="mb-1">{{ translate('messages.delivery_fee') }} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                            <input type="number" name="delivery_fee" class="form-control" min="0" max="999" step="0.001" required value="{{ number_format((float) $item->delivery_fee, (int) (config('round_up_to_digit') ?: 3), '.', '') }}">
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="submit" class="btn btn--primary btn-block">Move</button>
                        </div>
                    </div>
                    <div class="cc-move-eta text-muted"></div>
                </form>
            </div>
            <form action="{{ route($routePrefix . '.cancel', $item->id) }}" method="post" class="cc-cancel text-right">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Cancel request</button>
            </form>
            @endif
        </div>
    @endforeach
</div>
