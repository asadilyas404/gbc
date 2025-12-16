<?php
/**
 * Vendor System Module Permissions - Quick Reference
 *
 * Use this array in your parent system for validation and form generation
 */

return [
    // All vendor module permissions
    'modules' => [
        'food',
        'order',
        'kitchen_orders',
        'restaurant_setup',
        'addon',
        'wallet',
        'employee',
        'my_shop',
        'chat',
        'campaign',
        'reviews',
        'pos',
        'subscription',
        'coupon',
        'report',
        'custom_role',
        'options_list',
        'shift_session',
        'printer_settings',
    ],

    // Module display labels
    'labels' => [
        'food' => 'Food',
        'order' => 'Order',
        'kitchen_orders' => 'Kitchen Orders',
        'restaurant_setup' => 'Restaurant Setup',
        'addon' => 'Addon',
        'wallet' => 'Wallet',
        'employee' => 'Employee',
        'my_shop' => 'My Shop',
        'chat' => 'Chat',
        'campaign' => 'Campaign',
        'reviews' => 'Reviews',
        'pos' => 'POS',
        'subscription' => 'Subscription',
        'coupon' => 'Coupon',
        'report' => 'Report',
        'custom_role' => 'Custom Role',
        'options_list' => 'Options List',
        'shift_session' => 'Shift Session',
        'printer_settings' => 'Printer Settings',
    ],

    // Conditional modules (only show under certain conditions)
    'conditional' => [
        'subscription' => [
            'condition' => 'restaurant_model != "commission"',
            'description' => 'Only show if restaurant model is not commission-based'
        ],
    ],
];

/**
 * Usage Example:
 *
 * // In Controller
 * $vendorModules = require 'VENDOR_MODULES_QUICK_REFERENCE.php';
 *
 * // Validation
 * $rules = [
 *     'modules' => 'required|array|min:1',
 *     'modules.*' => 'required|string|in:' . implode(',', $vendorModules['modules']),
 * ];
 *
 * // In Blade View
 * @foreach($vendorModules['modules'] as $module)
 *     <input type="checkbox" name="modules[]" value="{{ $module }}" id="{{ $module }}">
 *     <label for="{{ $module }}">{{ $vendorModules['labels'][$module] }}</label>
 * @endforeach
 *
 * // Storage
 * $role->modules = json_encode($request->modules);
 */

