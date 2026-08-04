@extends('layouts.vendor.app')

@section('title', translate('Copy Partner Prices'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-2 text-capitalize">
                <div class="card-header-icon d-inline-flex mr-2 img">
                    <img src="{{ dynamicAsset('public/assets/admin/img/export.png') }}" alt="">
                </div>
                {{ translate('Copy Partner Prices') }}
            </h1>
        </div>

        <div class="card mb-2">
            <div class="card-body">
                <div class="jumbotron pt-1 pb-4 mb-0 bg-white">
                    <h2 class="mb-3 text-primary">{{ translate('Instructions') }}</h2>
                    <p>{{ translate('1. Select a source: Food (base price) or an existing delivery partner.') }}</p>
                    <p>{{ translate('2. Select the target delivery partner to receive the copied prices.') }}</p>
                    <p>{{ translate('3. This will overwrite existing prices for the target partner on all foods of this restaurant, including variation option extras.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('vendor.food.copy-partner-prices-data') }}" method="POST"
                    onsubmit="return confirm('{{ translate('This will overwrite existing prices for the target partner on all foods. Continue?') }}');">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label" for="source">{{ translate('Source') }}</label>
                                <select name="source" id="source" class="form-control" required>
                                    <option value="">{{ translate('Select source') }}</option>
                                    <option value="food">{{ translate('Food (base price)') }}</option>
                                    @foreach ($partners as $partner)
                                        <option value="{{ $partner->partner_id }}">{{ $partner->partner_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="input-label" for="target_partner_id">{{ translate('Target Partner') }}</label>
                                <select name="target_partner_id" id="target_partner_id" class="form-control" required>
                                    <option value="">{{ translate('Select target partner') }}</option>
                                    @foreach ($partners as $partner)
                                        <option value="{{ $partner->partner_id }}">{{ $partner->partner_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="submit" class="btn btn--primary">{{ translate('Copy Prices') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
