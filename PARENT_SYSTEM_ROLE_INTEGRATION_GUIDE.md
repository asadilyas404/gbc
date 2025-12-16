# Parent System Role Management Integration Guide

## Overview
This guide provides instructions for integrating vendor system role permissions into the parent system's role management. The parent system will manage roles that include vendor module permissions, and these roles will be automatically applied to the vendor system through the shared database.

---

## Database Structure

### Table: `employee_roles`

The vendor system uses the `employee_roles` table with the following structure:

```sql
CREATE TABLE employee_roles (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    modules TEXT NULL,              -- JSON encoded array of module names
    status BOOLEAN DEFAULT 1,
    restaurant_id BIGINT NULL,      -- NULL for parent system roles
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    is_pushed VARCHAR(1) NULL
);
```

### Key Fields:
- **`modules`**: Stores permissions as a JSON-encoded array (e.g., `["food", "order", "kitchen_orders"]`)
- **`restaurant_id`**: 
  - For parent system roles: Set to `NULL` or specific restaurant IDs
  - For vendor system roles: Contains the restaurant ID
- **`name`**: Role name (supports translations via `translations` table)

---

## Vendor Module Permissions - Complete List

### All Vendor Module Checkboxes Required in Parent System

When creating/editing roles in the parent system, you must include the following checkboxes for vendor module permissions:

| Checkbox Value | Display Label | Description |
|---------------|---------------|-------------|
| `food` | Food | Food/Item management |
| `order` | Order | Order management |
| `kitchen_orders` | Kitchen Orders | Kitchen order management |
| `restaurant_setup` | Restaurant Setup | Restaurant configuration |
| `addon` | Addon | Add-on management |
| `wallet` | Wallet | Wallet functionality |
| `employee` | Employee | Employee management |
| `my_shop` | My Shop | Shop management |
| `chat` | Chat | Chat/messaging |
| `campaign` | Campaign | Campaign management |
| `reviews` | Reviews | Review management |
| `pos` | POS | Point of Sale system |
| `subscription` | Subscription | Subscription management (conditional - only if restaurant_model != 'commission') |
| `coupon` | Coupon | Coupon management |
| `report` | Report | Reporting features |
| `custom_role` | Custom Role | Custom role management |
| `options_list` | Options List | Options list management |
| `shift_session` | Shift Session | Shift session management |
| `printer_settings` | Printer Settings | Printer settings management |

**Total: 19 vendor modules** (18 always visible + 1 conditional)

---

## HTML Form Implementation

### Checkbox Structure

Each checkbox must follow this exact format:

```html
<div class="check-item">
    <div class="form-group form-check form--check">
        <input type="checkbox" 
               name="modules[]" 
               value="MODULE_VALUE" 
               class="form-check-input system-checkbox" 
               id="MODULE_VALUE">
        <label class="form-check-label input-label qcont" 
               for="MODULE_VALUE">
            MODULE_DISPLAY_NAME
        </label>
    </div>
</div>
```

### Complete HTML Code for All Vendor Modules

```html
<!-- Module Permissions Section -->
<div class="d-flex">
    <h5 class="input-label m-0 text-capitalize">
        Vendor Module Permissions:
    </h5>
    <div class="check-item pb-0 w-auto">
        <div class="form-group form-check form--check m-0 ml-2">
            <input type="checkbox" 
                   class="form-check-input system-checkbox" 
                   id="allVendorModules">
            <label class="form-check-label ml-0" 
                   for="allVendorModules">Select All</label>
        </div>
    </div>
</div>

<div class="check--item-wrapper mx-0">
    <!-- Food -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="food"
                   class="form-check-input system-checkbox" id="food">
            <label class="form-check-label input-label qcont" for="food">Food</label>
        </div>
    </div>

    <!-- Order -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="order"
                   class="form-check-input system-checkbox" id="order">
            <label class="form-check-label input-label qcont" for="order">Order</label>
        </div>
    </div>

    <!-- Kitchen Orders -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="kitchen_orders"
                   class="form-check-input system-checkbox" id="kitchen_orders">
            <label class="form-check-label input-label qcont" for="kitchen_orders">Kitchen Orders</label>
        </div>
    </div>

    <!-- Restaurant Setup -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="restaurant_setup"
                   class="form-check-input system-checkbox" id="restaurant_setup">
            <label class="form-check-label input-label qcont" for="restaurant_setup">Restaurant Setup</label>
        </div>
    </div>

    <!-- Addon -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="addon"
                   class="form-check-input system-checkbox" id="addon">
            <label class="form-check-label input-label qcont" for="addon">Addon</label>
        </div>
    </div>

    <!-- Wallet -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="wallet"
                   class="form-check-input system-checkbox" id="wallet">
            <label class="form-check-label input-label qcont" for="wallet">Wallet</label>
        </div>
    </div>

    <!-- Employee -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="employee"
                   class="form-check-input system-checkbox" id="employee">
            <label class="form-check-label input-label qcont" for="employee">Employee</label>
        </div>
    </div>

    <!-- My Shop -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="my_shop"
                   class="form-check-input system-checkbox" id="my_shop">
            <label class="form-check-label input-label qcont" for="my_shop">My Shop</label>
        </div>
    </div>

    <!-- Chat -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="chat"
                   class="form-check-input system-checkbox" id="chat">
            <label class="form-check-label input-label qcont" for="chat">Chat</label>
        </div>
    </div>

    <!-- Campaign -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="campaign"
                   class="form-check-input system-checkbox" id="campaign">
            <label class="form-check-label input-label qcont" for="campaign">Campaign</label>
        </div>
    </div>

    <!-- Reviews -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="reviews"
                   class="form-check-input system-checkbox" id="reviews">
            <label class="form-check-label input-label qcont" for="reviews">Reviews</label>
        </div>
    </div>

    <!-- POS -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="pos"
                   class="form-check-input system-checkbox" id="pos">
            <label class="form-check-label input-label qcont" for="pos">POS</label>
        </div>
    </div>

    <!-- Subscription (Conditional - only show if restaurant_model != 'commission') -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="subscription"
                   class="form-check-input system-checkbox" id="subscription">
            <label class="form-check-label input-label qcont" for="subscription">Subscription</label>
        </div>
    </div>

    <!-- Coupon -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="coupon"
                   class="form-check-input system-checkbox" id="coupon">
            <label class="form-check-label input-label qcont" for="coupon">Coupon</label>
        </div>
    </div>

    <!-- Report -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="report"
                   class="form-check-input system-checkbox" id="report">
            <label class="form-check-label input-label qcont" for="report">Report</label>
        </div>
    </div>

    <!-- Custom Role -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="custom_role"
                   class="form-check-input system-checkbox" id="custom_role">
            <label class="form-check-label input-label qcont" for="custom_role">Custom Role</label>
        </div>
    </div>

    <!-- Options List -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="options_list"
                   class="form-check-input system-checkbox" id="options_list">
            <label class="form-check-label input-label qcont" for="options_list">Options List</label>
        </div>
    </div>

    <!-- Shift Session -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="shift_session"
                   class="form-check-input system-checkbox" id="shift_session">
            <label class="form-check-label input-label qcont" for="shift_session">Shift Session</label>
        </div>
    </div>

    <!-- Printer Settings -->
    <div class="check-item">
        <div class="form-group form-check form--check">
            <input type="checkbox" name="modules[]" value="printer_settings"
                   class="form-check-input system-checkbox" id="printer_settings">
            <label class="form-check-label input-label qcont" for="printer_settings">Printer Settings</label>
        </div>
    </div>
</div>
```

### JavaScript for "Select All" Functionality

```javascript
// Select All Vendor Modules
$('#allVendorModules').change(function() {
    var isChecked = $(this).is(':checked');
    $('.system-checkbox').not('#allVendorModules').prop('checked', isChecked);
});

// Uncheck "Select All" if any individual checkbox is unchecked
$('.system-checkbox').not('#allVendorModules').change(function() {
    if (!$(this).is(':checked')) {
        $('#allVendorModules').prop('checked', false);
    } else {
        // Check if all are selected
        var totalCheckboxes = $('.system-checkbox').not('#allVendorModules').length;
        var checkedCheckboxes = $('.system-checkbox:checked').not('#allVendorModules').length;
        if (totalCheckboxes === checkedCheckboxes) {
            $('#allVendorModules').prop('checked', true);
        }
    }
});
```

---

## How Permissions Are Stored

### Storage Format

Permissions are stored in the `modules` column as a **JSON-encoded array** of module names.

### Example Storage

**Input (from form):**
```php
$request->modules = ['food', 'order', 'kitchen_orders', 'restaurant_setup'];
```

**Storage (in database):**
```php
$role->modules = json_encode($request->modules);
// Result in database: "[\"food\",\"order\",\"kitchen_orders\",\"restaurant_setup\"]"
```

**Database Value:**
```
["food","order","kitchen_orders","restaurant_setup"]
```

### Retrieval

When reading from the database:
```php
$modules = json_decode($role->modules, true);
// Returns: ['food', 'order', 'kitchen_orders', 'restaurant_setup']
```

---

## Controller Implementation (Parent System)

### Store Method Example

```php
<?php

namespace App\Http\Controllers\Parent;

use App\Models\EmployeeRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Store a new role with vendor permissions
     */
    public function store(Request $request)
    {
        // Validation
        $request->validate([
            'name' => 'required|string|max:100',
            'modules' => 'required|array|min:1',
            'modules.*' => 'string|in:food,order,kitchen_orders,restaurant_setup,addon,wallet,employee,my_shop,chat,campaign,reviews,pos,subscription,coupon,report,custom_role,options_list,shift_session,printer_settings',
        ]);

        DB::beginTransaction();
        try {
            // Get next ID (vendor system uses manual ID assignment)
            $maxId = EmployeeRole::max('id');
            $nextId = $maxId ? $maxId + 1 : 1;

            // Create role in employee_roles table
            $role = new EmployeeRole();
            $role->id = $nextId;
            $role->name = $request->name;
            
            // CRITICAL: Store modules as JSON-encoded array
            $role->modules = json_encode($request->modules);
            
            $role->status = 1;
            $role->restaurant_id = null; // NULL for parent system roles
            $role->save();

            // If you need to sync to specific restaurants, do it here
            if ($request->has('sync_to_restaurants')) {
                $this->syncToRestaurants($role, $request->sync_to_restaurants);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Role created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update existing role
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'modules' => 'required|array|min:1',
            'modules.*' => 'string|in:food,order,kitchen_orders,restaurant_setup,addon,wallet,employee,my_shop,chat,campaign,reviews,pos,subscription,coupon,report,custom_role,options_list,shift_session,printer_settings',
        ]);

        $role = EmployeeRole::findOrFail($id);
        
        // CRITICAL: Update modules as JSON-encoded array
        $role->name = $request->name;
        $role->modules = json_encode($request->modules);
        $role->save();

        // Sync changes to restaurants if needed
        if ($request->has('sync_to_restaurants')) {
            $this->syncToRestaurants($role, $request->sync_to_restaurants);
        }

        return redirect()->back()->with('success', 'Role updated successfully');
    }

    /**
     * Sync parent role to vendor system restaurants
     */
    private function syncToRestaurants($parentRole, $restaurantIds)
    {
        foreach ($restaurantIds as $restaurantId) {
            $maxId = EmployeeRole::max('id');
            $nextId = $maxId ? $maxId + 1 : 1;

            EmployeeRole::updateOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    // You may want to add a parent_role_id field to track relationship
                ],
                [
                    'id' => $nextId,
                    'name' => $parentRole->name,
                    'modules' => $parentRole->modules, // Same JSON format
                    'status' => $parentRole->status,
                    'restaurant_id' => $restaurantId,
                ]
            );
        }
    }
}
```

---

## Validation Rules

### Required Validation

```php
$rules = [
    'name' => 'required|string|max:100',
    'modules' => 'required|array|min:1',
    'modules.*' => 'required|string|in:food,order,kitchen_orders,restaurant_setup,addon,wallet,employee,my_shop,chat,campaign,reviews,pos,subscription,coupon,report,custom_role,options_list,shift_session,printer_settings',
];
```

### Allowed Module Values (for validation)

```php
$allowedModules = [
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
    'printer_settings'
];
```

---

## How Vendor System Reads Permissions

### Permission Check Logic

The vendor system checks permissions using this logic:

```php
// From: app/CentralLogics/helpers.php
public static function employee_module_permission_check($mod_name)
{
    if (auth('vendor_employee')->check()) {
        $permission = auth('vendor_employee')->user()->role->modules;
        
        // Decode JSON and check if module exists in array
        if (isset($permission) && in_array($mod_name, (array) json_decode($permission)) == true) {
            return true;
        }
    }
    return false;
}
```

### Key Points:
1. **JSON Decode**: `json_decode($role->modules)` converts JSON string to array
2. **Array Check**: `in_array($mod_name, $modules)` checks if module is allowed
3. **Exact Match**: Module names must match exactly (case-sensitive)

---

## Database Examples

### Example 1: Role with Food and Order Permissions

```sql
INSERT INTO employee_roles (id, name, modules, status, restaurant_id, created_at, updated_at)
VALUES (
    1,
    'Manager',
    '["food","order"]',
    1,
    NULL,
    NOW(),
    NOW()
);
```

### Example 2: Role with Multiple Permissions

```sql
INSERT INTO employee_roles (id, name, modules, status, restaurant_id, created_at, updated_at)
VALUES (
    2,
    'Full Access',
    '["food","order","kitchen_orders","restaurant_setup","addon","wallet","employee","my_shop","chat","campaign","reviews","pos","coupon","report","custom_role","options_list","shift_session","printer_settings"]',
    1,
    NULL,
    NOW(),
    NOW()
);
```

### Example 3: Role Synced to Restaurant

```sql
INSERT INTO employee_roles (id, name, modules, status, restaurant_id, created_at, updated_at)
VALUES (
    3,
    'Manager',
    '["food","order","kitchen_orders"]',
    1,
    123,  -- Specific restaurant ID
    NOW(),
    NOW()
);
```

---

## Testing Checklist

- [ ] All 19 vendor module checkboxes are displayed in role creation form
- [ ] Checkbox values match exactly (case-sensitive): `food`, `order`, etc.
- [ ] Form submits `modules[]` as array
- [ ] Controller stores `modules` as JSON-encoded string: `json_encode($request->modules)`
- [ ] Database stores JSON string in `modules` column
- [ ] Can retrieve and decode: `json_decode($role->modules, true)` returns array
- [ ] "Select All" checkbox works correctly
- [ ] Validation accepts only valid module names
- [ ] Role can be created with at least one module selected
- [ ] Role can be updated with different modules
- [ ] Synced roles appear in vendor system

---

## Important Notes

1. **JSON Format is Critical**: The `modules` column MUST store a JSON-encoded array. Using comma-separated strings or other formats will break the vendor system.

2. **Module Names are Case-Sensitive**: `food` is correct, `Food` or `FOOD` will not work.

3. **Array Format**: Always use `json_encode()` when storing and `json_decode($json, true)` when reading.

4. **Restaurant ID**: 
   - Parent system roles: Use `NULL` or specific restaurant IDs
   - Vendor system will filter by `restaurant_id` when displaying roles

5. **ID Management**: Vendor system uses manual ID assignment. Ensure IDs don't conflict.

6. **Status Field**: Set `status = 1` for active roles, `status = 0` for inactive.

---

## Support

For questions or issues:
- Refer to vendor system code: `app/Http/Controllers/Vendor/CustomRoleController.php`
- Check permission check logic: `app/CentralLogics/helpers.php` → `employee_module_permission_check()`
- Review vendor form: `resources/views/vendor-views/custom-role/create.blade.php`

---

**Document Version:** 1.0  
**Last Updated:** 2025-01-XX  
**Compatible With:** Vendor System Role Management

