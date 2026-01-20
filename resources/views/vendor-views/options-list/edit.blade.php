@extends('layouts.vendor.app')

@section('title', translate('option_update'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title"><i class="tio-edit"></i> {{translate('messages.option_update')}}</h1>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('vendor.options-list.update',[$option['id']])}}" method="post" class="row">
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
                    @endif

                    @if ($language)
                    <div class="form-group lang_form col-md-6" id="default-form">
                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.option_name')}}" value="{{ $option->getRawOriginal('name')}}" required maxlength="191">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                        @foreach(json_decode($language) as $lang)
                            <?php
                                if(count($option['translations'])){
                                    $translate = [];
                                    foreach($option['translations'] as $t)
                                    {
                                        if($t->locale == $lang && $t->key=="name"){
                                            $translate[$lang]['name'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                            <div class="col-md-6 form-group d-none lang_form" id="{{$lang}}-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.option_name')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}" {{$lang == $default_lang? 'required':''}}  >
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        @endforeach
                    @else
                        <div class="form-group lang_form col-md-6" id="default-form">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                            <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.option_name')}}" value="{{ $option['name'] }}" required maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    @endif

                    <div class="form-group col-md-6">
                        <label class="form-label" for="exampleFormControlInput1">{{ translate('messages.price') }}</label>
                        <input type="number" min="0" max="999999999999.99" name="price" step="0.01"
                            class="form-control h--45px" placeholder="{{ translate('Ex : 100.00') }}"
                            value="{{ $option->price ?? old('price') }}">
                    </div>

                    <div class="col-12">
                        <div class="btn--container justify-content-end">
                            <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                        </div>
                    </div>
                </form>
            </div>
            <!-- End Table -->
        </div>
    </div>

@endsection

@push('script_2')
<script>
        "use strict";
        $(document).on('ready', function () {
            // Language switching functionality
            $(".lang_link").click(function(e) {
                e.preventDefault();
                $(".lang_link").removeClass('active');
                $(".lang_form").addClass('d-none');
                $(this).addClass('active');
                let form_id = this.id;
                let lang = form_id.substring(0, form_id.length - 5);
                $("#" + lang + "-form").removeClass('d-none');
                if (lang === "default") {
                    $(".default-form").removeClass("d-none");
                }
            });
        });
</script>
@endpush
