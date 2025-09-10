@php
use Illuminate\Support\Facades\DB;
@endphp
<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <!-- Logo -->
                <div class="sidebar-logo-container">
                    @php($restaurant_data = \App\CentralLogics\Helpers::get_restaurant_data())
                    <a class="navbar-brand pt-0 pb-0" href="{{ route('vendor.dashboard') }}" aria-label="Front">
                        <img class="navbar-brand-logo sidebar--logo-design" src="{{ $restaurant_data->logo_full_url }}"
                            alt="image">
                        <img class="navbar-brand-logo-mini sidebar--logo-design-2"
                            src="{{ $restaurant_data->logo_full_url }}" alt="image">

                        <div class="ps-2">
                            <h6>
                                {{ \Illuminate\Support\Str::limit($restaurant_data->name, 15) }}
                            </h6>
                        </div>
                    </a>
                    <!-- End Logo -->

                    <!-- Navbar Vertical Toggle -->
                    <button type="button"
                        class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                        <i class="tio-clear tio-lg"></i>
                    </button>
                    <!-- End Navbar Vertical Toggle -->
                </div>
                <div class="navbar-nav-wrap-content-left ml-auto d-none d-xl-block">
                    <!-- Navbar Vertical Toggle -->
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                            data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                            data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                    <!-- End Navbar Vertical Toggle -->
                </div>

            </div>

            <!-- Content -->
            <div class="navbar-vertical-content text-capitalize bg-334257">
                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <!-- Dashboards -->
                    <li class="pt-4"></li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('vendor.dashboard') }}"
                            title="{{ translate('messages.dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.dashboard') }}
                            </span>
                        </a>
                    </li>
                    <!-- End Dashboards -->

                    <!-- POS -->
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('pos'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/pos/new') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.pos.index.new') }}" title="{{ translate('POS') }}">
                                <i class="tio-shopping nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.pos') }}</span>
                            </a>
                        </li>
                    @endif
                    <!-- End POS -->
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('kitchen_orders'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/kitchen/list') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.kitchen.index') }}">
                                <i class="tio-shopping nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Kitchen
                                    Orders</span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('order'))
                        {{-- <li class="nav-item">
                            <small class="nav-subtitle"
                                title="{{ translate('messages.order_section') }}">{{ translate('messages.order_management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li> --}}

                        <!-- Order -->
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/order*') && Request::is('restaurant-panel/order/subscription*') == false ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.orders') }}">
                                <i class="tio-shopping-cart nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.orders') }}
                                </span>
                            </a>


                            @php($data = 0)
                            @php($restaurant = \App\CentralLogics\Helpers::get_restaurant_data())
                            @if (
                                ($restaurant->restaurant_model == 'subscription' &&
                                    isset($restaurant->restaurant_sub) &&
                                    $restaurant->restaurant_sub->self_delivery == 1) ||
                                    ($restaurant->restaurant_model == 'commission' && $restaurant->self_delivery_system == 1))
                                @php($data = 1)
                            @endif

                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:  {{ Request::is('restaurant-panel/order*') && Request::is('restaurant-panel/order/subscription*') == false ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/order/list/all') ? 'active' : '' }} @yield('all_order') ">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['all']) }}"
                                        title="{{ translate('messages.all_order') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('messages.all') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \App\Models\Order::where('restaurant_id', \App\CentralLogics\Helpers::get_restaurant_id())->whereExists(function ($query) { $query->select(DB::raw(1))->from('tbl_soft_branch')->whereColumn('tbl_soft_branch.branch_id', 'orders.restaurant_id')->whereColumn('tbl_soft_branch.orders_date', 'orders.order_date'); })->Notpos()->hasSubscriptionToday()->count() }}

                                                {{-- ->where(function ($query) use ($data) {
                                                        return $query->whereNotIn(
                                                                'order_status',
                                                                config('order_confirmation_model') == 'restaurant' || $data
                                                                    ? ['failed', 'canceled', 'refund_requested', 'refunded']
                                                                    : ['pending', 'failed', 'canceled', 'refund_requested', 'refunded'],
                                                            )->orWhere(function ($query) {
                                                                return $query->where('order_status', 'pending');
                                                                //->where('order_type', 'take_away')
                                                            });
                                                    })->Notpos()->HasSubscriptionToday()->NotDigitalOrder()->count() --}}

                                            </span>
                                        </span>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/order/list/draft') ? 'active' : '' }} @yield('all_order') ">
                                    <a class="nav-link" href="{{ route('vendor.order.list', ['draft']) }}"
                                        title="{{ translate('messages.Unpaid') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('messages.Unpaid') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \App\Models\Order::where('restaurant_id', \App\CentralLogics\Helpers::get_restaurant_id())->where('payment_status', 'unpaid')->whereExists(function ($query) { $query->select(DB::raw(1))->from('tbl_soft_branch')->whereColumn('tbl_soft_branch.branch_id', 'orders.restaurant_id')->whereColumn('tbl_soft_branch.orders_date', 'orders.order_date'); })->Notpos()->hasSubscriptionToday()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>

                            </ul>
                        </li>

                        {{-- @if ($restaurant->order_subscription_active == 1 || \App\Models\Order::where('restaurant_id', \App\CentralLogics\Helpers::get_restaurant_id())->whereNotNull('subscription_id')->count() > 0) --}}
                        {{-- <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/order/subscription*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.order.subscription.index') }}"
                                title="{{ translate('messages.order_subscriptiona') }}">
                                <i class="tio-appointment nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.order') }} {{ translate('messages.subscription') }}
                                </span>
                            </a>
                        </li> --}}
                        {{-- @endif --}}

                        <!-- End Order -->
                    @endif


                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('messages.food_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('food'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/category*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.categories') }}">
                                <i class="tio-category nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.categories') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('restaurant-panel/category*') ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/category/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.category.add') }}"
                                        title="{{ translate('messages.category') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.category') }}</span>
                                    </a>
                                </li>

                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/category/sub-category-list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.category.add-sub-category') }}"
                                        title="{{ translate('messages.sub_category') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.sub_category') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!-- Food -->
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/food*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('Food') }}">
                                <i class="tio-premium-outlined nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.foods') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('restaurant-panel/food*') ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/food/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.food.add-new') }}"
                                        title="{{ translate('Add New Food') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.add_new') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/food/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.food.add-new') }}?type=offer"
                                        title="{{ translate('Add New Food Offer') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.add_new') }} Offer</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('restaurant-panel/food/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.food.list') }}"
                                        title="{{ translate('Food List') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>
                                @if (\App\CentralLogics\Helpers::get_restaurant_data()->food_section)
                                    <li
                                        class="nav-item {{ Request::is('restaurant-panel/food/bulk-import') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.food.bulk-import') }}"
                                            title="{{ translate('Bulk Import') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate text-capitalize">{{ translate('messages.bulk_import') }}</span>
                                        </a>
                                    </li>
                                    <li
                                        class="nav-item {{ Request::is('restaurant-panel/food/bulk-export') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('vendor.food.bulk-export-index') }}"
                                            title="{{ translate('Bulk Export') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate text-capitalize">{{ translate('messages.bulk_export') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                        <!-- End Food -->
                    @endif
                    <!-- AddOn -->
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('addon'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/addon*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.addon.add-new') }}"
                                title="{{ translate('messages.addons') }}">
                                <i class="tio-add-circle-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.addons') }}
                                </span>
                            </a>
                        </li>
                    @endif
                    <!-- End AddOn -->
                    <!-- Options List -->
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('options_list'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/options-list*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.options-list.add-new') }}"
                                title="{{ translate('messages.options_list') }}">
                                <i class="tio-add-circle-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.options_list') }}
                                </span>
                            </a>
                        </li>
                    @endif
                    <!-- End Options List -->
                    <!-- Shift Session -->
                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('shift_session'))
                    <li
                        class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/shift-session*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                            href="{{ route('vendor.shift-session.index') }}"
                            title="Shift Session">
                            <i class="tio-time nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('messages.shift_session') }}
                            </span>
                        </a>
                    </li>
                    @endif
                    <!-- End Shift Session -->
                    <!-- Employee-->
                    {{-- <li class="nav-item">
                        <small class="nav-subtitle"
                            title="{{ translate('messages.Report_section') }}">{{ translate('messages.Report_section') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li> --}}

                    {{-- @if (\App\CentralLogics\Helpers::employee_module_permission_check('report'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/expense-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.expense-report') }}"
                                title="{{ translate('messages.expense_report') }}">
                                <span class="tio-money nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.expense_report') }}</span>
                            </a>
                        </li>


                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/transaction-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.day-wise-report') }}"
                                title="{{ translate('messages.transaction_report') }}">
                                <span class="tio-chart-pie-1 nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.transaction_report') }}</span>
                            </a>
                        </li>

                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/disbursement-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.disbursement-report') }}"
                                title="{{ translate('messages.disbursement_report') }}">
                                <span class="tio-saving nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.disbursement_report') }}</span>
                            </a>
                        </li>


                        <li
                            class="navbar-vertical-aside-has-menu  {{ Request::is('restaurant-panel/report/order-report') || Request::is('restaurant-panel/report/campaign-order-report') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.Order_Report') }}">
                                <i class="tio-user nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Order_Report') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('restaurant-panel/report/order-report') || Request::is('restaurant-panel/report/campaign-order-report') ? 'block' : 'none' }}">
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/order-report') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.report.order-report') }}"
                                        title="{{ translate('messages.order_report') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate text-capitalize">{{ translate('messages.Regular_order_report') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/campaign-order-report') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.report.campaign_order-report') }}"
                                        title="{{ translate('messages.Campaign_Order_Report') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate text-capitalize">{{ translate('messages.Campaign_Order_Report') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/report/food-wise-report') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('vendor.report.food-wise-report') }}"
                                title="{{ translate('messages.food_report') }}">
                                <span class="tio-fastfood nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.food_report') }}</span>
                            </a>
                        </li>
                    @endif --}}
                    <!-- Employee-->
                    <li class="nav-item">
                        <small class="nav-subtitle"
                            title="{{ translate('messages.employee_section') }}">{{ translate('messages.employee_section') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('custom_role'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/custom-role*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                                href="{{ route('vendor.custom-role.create') }}"
                                title="{{ translate('messages.employee_Role') }}">
                                <i class="tio-incognito nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.employee_Role') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::employee_module_permission_check('employee'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/employee*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.employees') }}">
                                <i class="tio-user nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.employees') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('restaurant-panel/employee*') ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/employee/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.employee.add-new') }}"
                                        title="{{ translate('messages.add_new_Employee') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.add_new_employee') }}</span>
                                    </a>
                                </li>
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/employee/list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('vendor.employee.list') }}"
                                        title="{{ translate('messages.Employee_list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>

                            </ul>
                        </li>
                    @endif
                    <!-- End Employee -->

                    <li
                        class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/printer/selection*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                            href="{{ route('vendor.printer.selection') }}"
                            title="{{ translate('messages.settings') }}">
                            <i class="tio-incognito nav-icon"></i>
                            <span
                                class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.settings') }}</span>
                        </a>
                    </li>

                    {{-- @if (\App\CentralLogics\Helpers::employee_module_permission_check('employee'))
                        <li
                            class="navbar-vertical-aside-has-menu {{ Request::is('restaurant-panel/employee*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                                title="{{ translate('messages.employees') }}">
                                <i class="tio-user nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.Setting') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{ Request::is('restaurant-panel/employee*') ? 'block' : 'none' }}">
                                <li
                                    class="nav-item {{ Request::is('restaurant-panel/employee/add-new') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('table_employees.index') }}"
                                        title="{{ translate('messages.add_new_Employee') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.table') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif --}}
                    <!-- End Employee -->

                    <!-- <li class="nav-item px-20 pb-5">
                        <div class="promo-card">
                            <div class="position-relative">
                                <img src="{{ dynamicAsset('public/assets/admin/img/promo.png') }}" class="mw-100" alt="">
                                <h4 class="mb-2 mt-3">{{ translate('Want_to_get_highlighted?') }}</h4>
                                <p class="mb-4">
                                    {{ translate('Create_ads_to_get_highlighted_on_the_app_and_web_browser') }}
                                </p>
                                <a href="{{ route('vendor.advertisement.create') }}" class="btn btn--primary">{{ translate('Create_Ads') }}</a>
                            </div>
                        </div>
                    </li> -->
                </ul>
            </div>
            <!-- End Content -->
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>



@push('script_2')
    <script>
        "use strict";
        $(window).on('load', function() {
            if ($(".navbar-vertical-content li.active").length) {
                $('.navbar-vertical-content').animate({
                    scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
                }, 100);
            }
        });
    </script>
@endpush
