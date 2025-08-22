@extends('layouts.vendor.app')

@section('title',translate('messages.add_new_option'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-add-circle-outlined"></i> {{translate('messages.add_new_option')}}</h1>
        </div>
        <!-- End Page Header -->
        <div class="card">
            <div class="card-body">
                <form action="{{route('vendor.options-list.store')}}" method="post" class="row">
                    @csrf
                    @php($language=\App\Models\BusinessSetting::where('key','language')->first())
                    @php($language = $language->value ?? null)
                    @php($default_lang = str_replace('_', '-', app()->getLocale()))
                    @if($language)
                        <div class="col-12">
                                <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default')}}</a>
                                </li>
                                @foreach(json_decode($language) as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{$lang}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang).'('.strtoupper($lang).')'}}</a>
                                    </li>
                                @endforeach
                            </ul>
                                </div>
                        </div>
                        <div class="form-group col-md-6 lang_form" id="default-form">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                            <input type="text" name="name[]" class="form-control" placeholder="{{ translate('messages.Ex :') }} {{translate('Option Name')}}" required maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                        @foreach(json_decode($language) as $lang)
                            <div class="form-group col-md-6 d-none lang_form" id="{{$lang}}-form">
                                <label class="form-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" class="form-control h--45px" placeholder="{{translate('Ex : Option Name ')}}" maxlength="191"   >
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        @endforeach
                    @else
                            <div class="form-group col-md-6 lang_form" id="default-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                                <input type="text" name="name[]" class="form-control" placeholder="{{ translate('messages.Ex :') }} {{translate('Option Name')}}"  required maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                    @endif

                    <div class="col-12">
                        <div class="btn--container justify-content-end">
                            <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('messages.submit')}}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <h5 class="card-title">
                        {{translate('messages.options_list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$options->total()}}</span>
                    </h5>
                    <div id="search-form">
                        <form >
                        <div class="input-group input--group">
                            <input autocomplete="false" type="text" class="d-none">
                            <input type="text" name="search" value="{{ request()->search ?? null }}"  class="form-control" placeholder="{{ translate('Ex : Search by Option Name') }}">
                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Table -->
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                       class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       data-hs-datatables-options='{
                         "order": [],
                         "orderCellsTop": true,
                         "paging":false
                       }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="w-100px">{{translate('messages.sl')}}</th>
                        <th class="w-70p">{{translate('messages.name')}}</th>
                        <th class="text-center w-100px">{{translate('messages.action')}}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($options as $key=>$option)
                        <tr>
                            <td>{{$key+1}}</td>
                            <td>
                            <span class="d-block font-size-sm text-body">
                                {{Str::limit($option['name'], 50, '...')}}
                            </span>
                            </td>
                            <td>
                                @if(isset($option['id']) && !empty($option['id']))
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                            href="{{route('vendor.options-list.edit', ['id' => $option['id'] ])}}" title="{{translate('messages.edit_option')}}"><i class="tio-edit"></i></a>
                                    <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                        data-id="option-{{$option['id']}}" data-message="{{ translate('Want to delete this option ?') }}" title="{{translate('messages.delete_option')}}"><i class="tio-delete-outlined"></i></a>
                                    <form action="{{route('vendor.options-list.delete',['id' => $option['id']])}}"
                                                method="post" id="option-{{$option['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($options) === 0)
                <div class="empty--data">
                    <img src="{{dynamicAsset('/public/assets/admin/img/empty.png')}}" alt="public">
                    <h5>
                        {{translate('no_data_found')}}
                    </h5>
                </div>
                @endif
                <table>
                    <tfoot>
                    {!! $options->links() !!}
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function () {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });

            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });
    </script>
@endpush
