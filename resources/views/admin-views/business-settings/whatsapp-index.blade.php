@extends('layouts.admin.app')

@section('title', translate('messages.Whatsapp_Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start">
                <h1 class="page-header-title mr-3">
                    <span class="page-header-icon">
                        <img src="{{ dynamicAsset('public/assets/admin/img/business.png') }}" class="w--20" alt="">
                    </span>
                    <span>
                        {{ translate('messages.business_setup') }}
                    </span>
                </h1>
                <div class="d-flex flex-wrap justify-content-end align-items-center flex-grow-1">
                    <div class="blinkings active">
                        <i class="tio-info-outined"></i>
                        <div class="business-notes">
                            <h6><img src="{{dynamicAsset('/public/assets/admin/img/notes.png')}}" alt=""> {{translate('Note')}}</h6>
                            <div>
                                {{translate('Don’t_forget_to_click_the_respective_‘Save_Information’_buttons_below_to_save_changes')}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>

        <form action="{{ route('admin.business-settings.update-whatsapp') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        @php ($enable_whatsapp = \App\Models\BusinessSetting::where('key', 'enable_whatsapp')->first()) @endphp
                                        @php ($enable_whatsapp = $enable_whatsapp ? $enable_whatsapp->value : 0) @endphp
                                        <label
                                            class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1">
                                                    {{ translate('Enable Whatsapp') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Customers_can_give_tips_to_deliverymen_during_checkout_from_the_Customer_App_&_Website._Admin_will_not_earn_any_commission_from_it.') }}"><img
                                                        src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('messages.whatsapp_hint') }}">  </span>
                                            </span>
                                            <input type="checkbox"
                                            data-id="enable_whatsapp"
                                            data-type="toggle"
                                            data-image-on="{{ dynamicAsset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                            data-image-off="{{ dynamicAsset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                            data-title-on="{{ translate('Want_to_enable_the') }} <strong>{{ translate('Whatsapp') }}</strong> {{ translate('feature') }} ?"
                                            data-title-off="{{ translate('Want_to_disable_the') }} <strong>{{ translate('Whatsapp') }}</strong> {{ translate('feature') }} ?"
                                            data-text-on="<p>{{ translate('If_enabled,_Customer_will_get_a_message_when_their_order_is_confirmed_and_ready_for_pickup') }}</p>"
                                            data-text-off="<p>{{ translate('If_disabled,_Customer_will_not_get_any_whatsapp_notifications_about_their_order') }}</p>"
                                            class="toggle-switch-input dynamic-checkbox-toggle"
                                            value="1"
                                                name="enable_whatsapp" id="enable_whatsapp"
                                                {{ $enable_whatsapp == '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        @php ($enable_whatsapp_number_popup = \App\Models\BusinessSetting::where('key', 'enable_whatsapp_number_popup')->first()) @endphp
                                        @php ($enable_whatsapp_number_popup = $enable_whatsapp_number_popup ? $enable_whatsapp_number_popup->value : 0) @endphp
                                        <label
                                            class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1">
                                                    {{ translate('Enable Whatsapp Number Popup') }}
                                                </span>
                                                <span class="form-label-secondary text-danger d-flex"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Customers_can_give_tips_to_deliverymen_during_checkout_from_the_Customer_App_&_Website._Admin_will_not_earn_any_commission_from_it.') }}"><img
                                                        src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('messages.whatsapp_hint') }}">  </span>
                                            </span>
                                            <input type="checkbox"
                                            data-id="enable_whatsapp_number_popup"
                                            data-type="toggle"
                                            data-image-on="{{ dynamicAsset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                            data-image-off="{{ dynamicAsset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                            data-title-on="{{ translate('Want_to_enable_the') }} <strong>{{ translate('Whatsapp') }}</strong> {{ translate('feature') }} ?"
                                            data-title-off="{{ translate('Want_to_disable_the') }} <strong>{{ translate('Whatsapp') }}</strong> {{ translate('feature') }} ?"
                                            data-text-on="<p>{{ translate('If_enabled,_empty_phone_number_will_show_popup_warning') }}</p>"
                                            data-text-off="<p>{{ translate('If_disabled,_empty_phone_number_will_not_show_popup_warning') }}</p>"
                                            class="toggle-switch-input dynamic-checkbox-toggle"
                                            value="1"
                                                name="enable_whatsapp_number_popup" id="enable_whatsapp_number_popup"
                                                {{ $enable_whatsapp_number_popup == '1' ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    @php($whatsapp_api_version = \App\Models\BusinessSetting::where('key', 'whatsapp_api_version')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="whatsapp_api_version">
                                            <div class="d-flex align-items-center">
                                                <span class="line--limit-1 flex-grow">{{ translate('WhatsApp API Version') }} </span>
                                            </div>
                                            <span class="form-label-secondary"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('Set_the_maximum_order_limit_a_Deliveryman_can_take_at_a_time') }}"><img
                                                src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}"
                                                alt="{{ translate('messages.dm_cancel_order_hint') }}"></span>
                                        </label>
                                        <input type="text" name="whatsapp_api_version" class="form-control"
                                            id="whatsapp_api_version" min="1"
                                            value="{{ $whatsapp_api_version ? $whatsapp_api_version->value : '' }}" required>
                                    </div>
                                </div>
                                
                                <div class="col-sm-6 col-lg-4">
                                    @php($whatsapp_phone_number_id = \App\Models\BusinessSetting::where('key', 'whatsapp_phone_number_id')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="whatsapp_phone_number_id">
                                            <span class="line--limit-1">
                                                {{translate('WhatsApp_Phone_Number_ID')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_WhatsApp_Phone_Number_ID')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        <input type="text" name="whatsapp_phone_number_id" class="form-control"
                                            id="whatsapp_phone_number_id"
                                            value="{{ $whatsapp_phone_number_id ? $whatsapp_phone_number_id->value : '' }}" required>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    @php($whatsapp_access_token = \App\Models\BusinessSetting::where('key', 'whatsapp_access_token')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="whatsapp_access_token">
                                            <span class="line--limit-1">
                                                {{translate('WhatsApp_Access_Token')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_WhatsApp_Access_Token')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        <input type="text" name="whatsapp_access_token" class="form-control"
                                            id="whatsapp_access_token"
                                            value="{{ $whatsapp_access_token ? $whatsapp_access_token->value : '' }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="card-title my-3">
                        <img src="{{dynamicAsset('/public/assets/admin/img/company.png')}}" class="card-header-icon mr-2" alt="">
                        <span>{{ translate('Whatsapp Timeout Settings') }}</span>
                    </h4>
                    <div class="card">
                        <div class="card-body">

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6 col-lg-6">
                                    @php($order_confirmation_whatsapp_timeout = \App\Models\BusinessSetting::where('key', 'order_confirmation_whatsapp_timeout')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="order_confirmation_whatsapp_timeout">
                                            <span class="line--limit-1">
                                                {{translate('Order_Confirmation_WhatsApp_Timeout')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_WhatsApp_Timeout_Duration_In_Minutes')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        <input type="number" step="1" min="0" name="order_confirmation_whatsapp_timeout" class="form-control"
                                            id="order_confirmation_whatsapp_timeout"
                                            value="{{ $order_confirmation_whatsapp_timeout ? $order_confirmation_whatsapp_timeout->value : 0 }}">
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-6">
                                    @php($order_ready_whatsapp_timeout = \App\Models\BusinessSetting::where('key', 'order_ready_whatsapp_timeout')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="order_ready_whatsapp_timeout">
                                            <span class="line--limit-1">
                                                {{translate('Order_Ready_WhatsApp_Timeout')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_WhatsApp_Timeout_Duration_In_Minutes')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        <input type="number" step="1" min="0" name="order_ready_whatsapp_timeout" class="form-control"
                                            id="order_ready_whatsapp_timeout"
                                            value="{{ $order_ready_whatsapp_timeout ? $order_ready_whatsapp_timeout->value : 0 }}">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <h4 class="card-title my-3">
                        <img src="{{dynamicAsset('/public/assets/admin/img/company.png')}}" class="card-header-icon mr-2" alt="">
                        <span>{{ translate('Whatsapp_Templates_Information') }}</span>
                    </h4>
                    <div class="card">
                        <div class="card-body">

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6 col-lg-6">
                                    @php($order_confirmation_whatsapp_template = \App\Models\BusinessSetting::where('key', 'order_confirmation_whatsapp_template')->first())
                                    <div class="form-group mb-0">
                                        <label class="input-label d-flex text-capitalize"
                                            for="order_confirmation_whatsapp_template">
                                            <span class="line--limit-1">
                                                {{translate('Order_Confirmation_WhatsApp_Template')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_Order_Confirmation_WhatsApp_Template')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        <input type="text" name="order_confirmation_whatsapp_template" class="form-control"
                                            id="order_confirmation_whatsapp_template"
                                            value="{{ $order_confirmation_whatsapp_template ? $order_confirmation_whatsapp_template->value : '' }}">
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-6">
                                    @php($order_ready_whatsapp_template = \App\Models\BusinessSetting::where('key', 'order_ready_whatsapp_template')->first())
                                    <div class="form-group mb-0">
    
                                        <label class="input-label d-flex text-capitalize"
                                            for="order_ready_whatsapp_template">
                                            <span class="line--limit-1">
                                                {{translate('Order_Ready_WhatsApp_Template')}}
                                            </span>
                                            </span>
                                            <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Enter_the_Order_Ready_WhatsApp_Template')}}" class="input-label-secondary"><img src="{{ dynamicAsset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.dm_maximum_order_hint') }}"></span>
                                        </label>
                                        
                                        <input type="text" name="order_ready_whatsapp_template" class="form-control"
                                            id="order_ready_whatsapp_template"
                                            value="{{ $order_ready_whatsapp_template ? $order_ready_whatsapp_template->value : '' }}">
                                    </div>
                                </div>

                            </div>

                            <div class="btn--container justify-content-end">
                                <button type="reset" id="reset_btn" class="btn btn--reset location-reload">{{ translate('messages.reset') }}</button>
                                <button type="submit" id="submit" class="btn btn--primary">{{ translate('messages.save_information') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
