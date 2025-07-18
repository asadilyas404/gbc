@extends('layouts.vendor.app')
@section('title', translate('Employee List'))
@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">


        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h2 class="page-header-title text-capitalize">
                        <div class="card-header-icon d-inline-flex mr-2 img">
                            <img src="{{ dynamicAsset('/public/assets/admin/img/resturant-panel/page-title/employee-role.png') }}"
                                alt="public">
                        </div>
                        <span>
                            {{ translate('messages.Employee_list') }}<span class="badge badge-soft-dark ml-2"
                                id="itemCount">{{ $em->total() }}</span>
                        </span>
                    </h2>
                </div>
                <div class="col-sm">
                    <a href="{{ route('vendor.employee.add-new') }}" class="btn btn--primary  float-right">
                        <i class="tio-add-circle"></i>
                        <span class="text">
                            {{ translate('Add New Employee') }}
                        </span>
                    </a>
                </div>
                @if (app()->environment('local'))
                    <div class="my-2">
                        <div class="row g-2 align-items-center justify-content-end">

                            <div class="col-auto">
                                <a href="{{ route('vendor.employee.sync.employees') }}"
                                    class="btn max-sm-12 btn--primary w-100">Sync
                                    Employees</a>
                            </div>

                        </div>
                    </div>
                @endif
            </div>
        </div>
        <!-- End Page Header -->

        <!-- Card -->
        <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    <span class="card-title"></span>
                    <form>

                        <!-- Search -->
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control"
                                placeholder="{{ translate('Ex : Search by Employee Name, Email or Phone No') }}"
                                value="{{ request()->search ?? null }}" aria-label="Search">
                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                        <!-- End Search -->
                    </form>

                    <!-- Export Button -->
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm"
                            href="javascript:;"
                            data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('vendor.employee.export-employee', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ dynamicAsset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                {{ translate('messages.excel') }}
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('vendor.employee.export-employee', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ dynamicAsset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                {{ translate('messages.csv') }}
                            </a>
                        </div>
                    </div>
                    <!-- Export Button -->

                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.sl') }}</th>
                                <th>{{ translate('messages.name') }}</th>
                                <th>{{ translate('messages.email') }}</th>
                                <th>{{ translate('messages.phone') }}</th>
                                <th>{{ translate('messages.Role') }}</th>
                                <th class="w-100px text-center">{{ translate('messages.action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($em as $k => $e)
                                <tr>
                                    <th scope="row">{{ $k + $em->firstItem() }}</th>
                                    <td class="text-capitalize text-break text-hover-primary">{{ $e['f_name'] }}
                                        {{ $e['l_name'] }}</td>
                                    <td>
                                        {{ $e['email'] }}
                                    </td>
                                    <td>{{ $e['phone'] }}</td>
                                    <td>{{ $e->role ? $e->role['name'] : translate('messages.role_deleted') }}</td>
                                    <td>

                                        @if (auth('vendor_employee')->id() != $e['id'])
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn btn--primary btn-outline-primary"
                                                    href="{{ route('vendor.employee.edit', [$e['id']]) }}"
                                                    title="{{ translate('messages.edit_Employee') }}"><i
                                                        class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn btn--danger btn-outline-danger form-alert"
                                                    href="javascript:" data-id="employee-{{ $e['id'] }}"
                                                    data-message="{{ translate('messages.Want_to_delete_this_role') }}"
                                                    title="{{ translate('messages.delete_Employee') }}"><i
                                                        class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{ route('vendor.employee.delete', [$e['id']]) }}"
                                                    method="post" id="employee-{{ $e['id'] }}">
                                                    @csrf @method('delete')
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($em) === 0)
                        <div class="empty--data">
                            <img src="{{ dynamicAsset('/public/assets/admin/img/empty.png') }}" alt="public">
                            <h5>
                                {{ translate('no_data_found') }}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <div class="page-area">
                    <table>
                        <tfoot>
                            {!! $em->links() !!}
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <!-- Card -->
    </div>
@endsection

@push('script_2')
@endpush
