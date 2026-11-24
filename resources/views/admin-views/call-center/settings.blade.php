@extends(auth('admin')->check() ? 'layouts.admin.app' : 'layouts.vendor.app')

@section('title', 'Call Center Settings')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-time"></i></span>
                <span>Call Center Settings</span>
            </h1>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        @if (!$installed)
            <div class="alert alert-warning">
                The call center tables are not installed yet. Run <code>php artisan call-center:install</code> on this server, then reload this page.
            </div>
        @else
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Times used for every quote</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route($routePrefix . '.settings.update') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="preparation_minutes">Preparation (minutes)</label>
                                    <input type="number" min="0" max="180" class="form-control" name="preparation_minutes"
                                        id="preparation_minutes" value="{{ old('preparation_minutes', $preparationMinutes) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="handover_minutes">Handover and payment (minutes)</label>
                                    <input type="number" min="0" max="60" class="form-control" name="handover_minutes"
                                        id="handover_minutes" value="{{ old('handover_minutes', $handoverMinutes) }}" required>
                                </div>
                            </div>
                        </div>
                        <p class="mb-3" id="eta-preview"></p>
                        <button type="submit" class="btn btn--primary">Save times</button>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add a delivery zone</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route($routePrefix . '.zones.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="restaurant_id">Branch</label>
                                    <select name="restaurant_id" id="restaurant_id" class="form-control" required>
                                        <option value="">Select branch</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->branch_id }}" {{ (string) old('restaurant_id') === (string) $branch->branch_id ? 'selected' : '' }}>
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="name">Zone name</label>
                                    <input type="text" name="name" id="name" class="form-control" maxlength="150"
                                        value="{{ old('name') }}" placeholder="Example: Al Khuwair" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="delivery_minutes">Drive from branch (minutes)</label>
                                    <input type="number" min="0" max="180" name="delivery_minutes" id="delivery_minutes"
                                        class="form-control" value="{{ old('delivery_minutes') }}" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn--primary">Add zone</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Zones</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                        <colgroup>
                            <col style="width: 28%;">
                            <col style="width: 22%;">
                            <col style="width: 14%;">
                            <col style="width: 16%;">
                            <col style="width: 20%;">
                        </colgroup>
                        <thead class="thead-light">
                            <tr>
                                <th class="text-nowrap">Branch</th>
                                <th class="text-nowrap">Zone</th>
                                <th class="text-nowrap">Drive (minutes)</th>
                                <th class="text-nowrap">Customer is told</th>
                                <th class="text-right text-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($zones as $zone)
                                @php($quote = \App\Services\CallCenter::quote((int) $zone->delivery_minutes))
                                <tr>
                                    <td>
                                        <select name="restaurant_id" class="form-control" form="zone-update-{{ $zone->id }}" required>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->branch_id }}" {{ (string) $zone->restaurant_id === (string) $branch->branch_id ? 'selected' : '' }}>
                                                    {{ $branch->branch_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="name" class="form-control" form="zone-update-{{ $zone->id }}" value="{{ $zone->name }}" maxlength="150" required>
                                    </td>
                                    <td>
                                        <input type="number" name="delivery_minutes" class="form-control" form="zone-update-{{ $zone->id }}" min="0" max="180" value="{{ $zone->delivery_minutes }}" required>
                                    </td>
                                    <td class="text-nowrap">{{ $quote['customer_eta_minutes'] }} minutes</td>
                                    <td class="text-right text-nowrap">
                                        <button type="submit" class="btn btn-sm btn--primary" form="zone-update-{{ $zone->id }}">Save</button>
                                        <button type="submit" class="btn btn-sm btn-outline-danger ml-1" form="zone-delete-{{ $zone->id }}" onclick="return confirm('Remove this zone?');">Remove</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No zones yet. Add a test zone for a branch above.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @foreach ($zones as $zone)
                    <form id="zone-update-{{ $zone->id }}" action="{{ route($routePrefix . '.zones.update', $zone->id) }}" method="post" class="d-none">
                        @csrf
                    </form>
                    <form id="zone-delete-{{ $zone->id }}" action="{{ route($routePrefix . '.zones.destroy', $zone->id) }}" method="post" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";
        function callCenterQuote(zoneMinutes) {
            const preparation = parseInt(document.getElementById('preparation_minutes').value, 10) || 0;
            const handover = parseInt(document.getElementById('handover_minutes').value, 10) || 0;
            const zone = parseInt(zoneMinutes, 10) || 0;
            return {
                customer: preparation + zone + handover,
                back: zone
            };
        }

        function refreshPreview() {
            const quote = callCenterQuote(15);
            const preview = document.getElementById('eta-preview');
            if (!preview) {
                return;
            }
            preview.textContent = 'A zone 15 minutes from the branch is quoted as ' + quote.customer +
                ' minutes. After drop-off, the driver is back at the branch in ' + quote.back + ' minutes.';
        }

        document.getElementById('preparation_minutes')?.addEventListener('input', refreshPreview);
        document.getElementById('handover_minutes')?.addEventListener('input', refreshPreview);
        refreshPreview();
    </script>
@endpush
