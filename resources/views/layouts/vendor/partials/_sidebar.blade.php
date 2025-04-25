<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <!-- Logo -->
                <div class="sidebar-logo-container">
                    @php($restaurant_data=\App\CentralLogics\Helpers::get_restaurant_data())
                    <a class="navbar-brand pt-0 pb-0" href="{{route('vendor.dashboard')}}" aria-label="Front">
                            <img class="navbar-brand-logo"
                            src="{{ $restaurant_data->logo_full_url }}"
                            alt="image">
                            <img class="navbar-brand-logo-mini"
                            src="{{ $restaurant_data->logo_full_url }}"
                            alt="image">

                        <div class="ps-2">
                            <h6>
                                {{\Illuminate\Support\Str::limit($restaurant_data->name,15)}}
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
                    <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel')?'active':''}}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                           href="{{route('vendor.dashboard')}}" title="{{translate('messages.dashboard')}}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{translate('messages.dashboard')}}
                            </span>
                        </a>
                    </li>
                    <!-- End Dashboards -->

                    <!-- POS -->
                    @if(\App\CentralLogics\Helpers::employee_module_permission_check('pos'))
                        <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel/pos/new')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" target="_blank" href="{{route('vendor.pos.index.new')}}" title="{{translate('POS')}}">
                                <i class="tio-shopping nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{translate('messages.pos')}}</span>
                            </a>
                        </li>
                    @endif
                    <!-- End POS -->
                    <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel/kitchen/list')?'active':''}}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" target="_blank" href="{{route('vendor.kitchen.index')}}">
                            <i class="tio-shopping nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Kitchen Orders</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <small class="nav-subtitle">{{translate('messages.food_management')}}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if(\App\CentralLogics\Helpers::employee_module_permission_check('food'))
                        <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel/category*')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle"
                               href="javascript:" title="{{translate('messages.categories')}}"
                            >
                                <i class="tio-category nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{translate('messages.categories')}}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{Request::is('restaurant-panel/category*')?'block':'none'}}">
                                <li class="nav-item {{Request::is('restaurant-panel/category/list')?'active':''}}">
                                    <a class="nav-link " href="{{route('vendor.category.add')}}"
                                       title="{{translate('messages.category')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{translate('messages.category')}}</span>
                                    </a>
                                </li>

                                <li class="nav-item {{Request::is('restaurant-panel/category/sub-category-list')?'active':''}}">
                                    <a class="nav-link " href="{{route('vendor.category.add-sub-category')}}"
                                       title="{{translate('messages.sub_category')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{translate('messages.sub_category')}}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!-- Food -->
                        <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel/food*')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{translate('Food')}}"
                            >
                                <i class="tio-premium-outlined nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{translate('messages.foods')}}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{Request::is('restaurant-panel/food*')?'block':'none'}}">
                                <li class="nav-item {{Request::is('restaurant-panel/food/add-new')?'active':''}}">
                                    <a class="nav-link " href="{{route('vendor.food.add-new')}}"
                                       title="{{translate('Add New Food')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{translate('messages.add_new')}}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{Request::is('restaurant-panel/food/add-new')?'active':''}}">
                                    <a class="nav-link " href="{{route('vendor.food.add-new')}}?type=offer"
                                       title="{{translate('Add New Food Offer')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{translate('messages.add_new')}} Offer</span>
                                    </a>
                                </li>
                                <li class="nav-item {{Request::is('restaurant-panel/food/list')?'active':''}}">
                                    <a class="nav-link " href="{{route('vendor.food.list')}}"  title="{{translate('Food List')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{translate('messages.list')}}</span>
                                    </a>
                                </li>
                                @if(\App\CentralLogics\Helpers::get_restaurant_data()->food_section)
                                    <li class="nav-item {{Request::is('restaurant-panel/food/bulk-import')?'active':''}}">
                                        <a class="nav-link " href="{{route('vendor.food.bulk-import')}}"
                                           title="{{translate('Bulk Import')}}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{translate('messages.bulk_import')}}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{Request::is('restaurant-panel/food/bulk-export')?'active':''}}">
                                        <a class="nav-link " href="{{route('vendor.food.bulk-export-index')}}"
                                           title="{{translate('Bulk Export')}}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span class="text-truncate text-capitalize">{{translate('messages.bulk_export')}}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                        <!-- End Food -->
                    @endif
                    <!-- AddOn -->
                    @if(\App\CentralLogics\Helpers::employee_module_permission_check('addon'))
                        <li class="navbar-vertical-aside-has-menu {{Request::is('restaurant-panel/addon*')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{route('vendor.addon.add-new')}}" title="{{translate('messages.addons')}}" >
                                <i class="tio-add-circle-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{translate('messages.addons')}}
                            </span>
                            </a>
                        </li>
                    @endif
                    <!-- End AddOn -->
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
    $(window).on('load' , function() {
        if($(".navbar-vertical-content li.active").length) {
            $('.navbar-vertical-content').animate({
                scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
            }, 100);
        }
        });
</script>
@endpush
