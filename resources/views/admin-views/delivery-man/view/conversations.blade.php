@extends('layouts.admin.app')

@section('title',translate('Delivery_Man_Preview'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-user"></i>
                </span>
                <span>{{$dm['f_name'].' '.$dm['l_name']}}</span>
            </h1>
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <!-- Nav -->
                <ul class="nav nav-tabs page-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'info'])}}"  aria-disabled="true">{{translate('messages.info')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'transaction'])}}"  aria-disabled="true">{{translate('messages.transaction')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'timelog'])}}"  aria-disabled="true">{{translate('messages.timelog')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'conversation'])}}"  aria-disabled="true">{{translate('messages.conversations')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'disbursement'])}}"  aria-disabled="true">{{translate('messages.disbursements')}}</a>
                    </li>
                </ul>
                <!-- End Nav -->
            </div>
        </div>
        <!-- End Page Header -->

        <div class="content container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-header-title">{{ translate('messages.conversation_list') }}</h1>
            </div>
            <!-- End Page Header -->

            <div class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <!-- Card -->
                    <div class="card">
                        <div class="card-header border-0">
                            <div class="input-group input---group">
                                <div class="input-group-prepend border-inline-end-0">
                                    <span class="input-group-text border-inline-end-0" id="basic-addon1"><i class="tio-search"></i></span>
                                </div>
                                <input type="text" class="form-control border-inline-start-0 pl-1" id="serach" placeholder="{{ translate('messages.search') }}" aria-label="Username"
                                    aria-describedby="basic-addon1" autocomplete="off">
                            </div>
                        </div>
                        <!-- Body -->
                        <div class="card-body p-0 initial-19" id="dm-conversation-list">
                            <div class="border-bottom"></div>
                            @include('admin-views.delivery-man.partials._conversation_list')
                        </div>
                        <!-- End Body -->
                    </div>
                    <!-- End Card -->
                </div>
                <div class="col-lg-8 col-nd-6" id="dm-view-conversation">
                    <div class="text-center mt-2">
                        <h4 class="initial-20">{{ translate('messages.view_conversation') }}
                        </h4>
                    </div>
                    {{-- view here --}}
                </div>
            </div>
            <!-- End Row -->
        </div>

    </div>
@endsection

@push('script_2')
<script>
// data-panel-scope: page-owned script; cleaned up before Turbo replaces the body.
(() => {
"use strict";
const __panel = PanelPage.create("resources_views_admin_views_delivery_man_view_conversations_blade_php_0");
__panel.globals({
"viewConvs": {get: () => viewConvs, set: value => { viewConvs = value; }},
"page": {get: () => page, set: value => { page = value; }},
"user_id": {get: () => user_id, set: value => { user_id = value; }},
"loadMoreData": {get: () => loadMoreData, set: value => { loadMoreData = value; }},
"fetch_data": {get: () => fetch_data, set: value => { fetch_data = value; }}
});



    $('.view-conv').on('click', function (){
        let url = $(this).data('url');
        let id_to_active = $(this).data('active-id');
        let conv_id = $(this).data('conv-id');
        let sender_id = $(this).data('sender-id');
        viewConvs(url, id_to_active, conv_id, sender_id);
    })

    function viewConvs(url, id_to_active, conv_id, sender_id) {
        $('.customer-list').removeClass('conv-active');
        $('#' + id_to_active).addClass('conv-active');
        let new_url= "{{route('admin.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'conversation'])}}" + '?conversation=' + conv_id+ '&user=' + sender_id;
            __panel.request($.get({
                url: url,
                success: function(data) {
                    window.history.pushState('', 'New Page Title', new_url);
                    $('#dm-view-conversation').html(data.view);
                }
            }));
    }

    let page = 1;
    let user_id =  $('#deliver_man').val();
    $('#dm-conversation-list').scroll(function() {
        if ($('#dm-conversation-list').scrollTop() + $('#dm-conversation-list').height() >= $('#dm-conversation-list')
            .height()) {
            page++;
            loadMoreData(page);
        }
    });

    function loadMoreData(page) {
        __panel.request($.ajax({
                url: "{{ route('admin.delivery-man.message-list-search') }}" + '?page=' + page,
                type: "get",
                data:{"user_id":user_id},
                beforeSend: function() {

                }
            }))
            .done(function(data) {
                if (data.html == " ") {
                    return;
                }
                $("#dm-conversation-list").append(data.html);
            })
            .fail(function(jqXHR, ajaxOptions, thrownError) {
                alert('server not responding...');
            });
    };

    function fetch_data(page, query) {
            __panel.request($.ajax({
                url: "{{ route('admin.delivery-man.message-list-search') }}" + '?page=' + page + "&key=" + query,
                type: "get",
                data:{"user_id":user_id},
                success: function(data) {
                    $('#dm-conversation-list').empty();
                    $("#dm-conversation-list").append(data.html);
                }
            }))
        };

        __panel.onTarget(document, 'keyup', '#serach', function() {
            let query = $('#serach').val();
            fetch_data(page, query);
        });

})();
</script>
@endpush
