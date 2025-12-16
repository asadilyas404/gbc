# Permission System Analysis - Previous Implementation

## Overview
This document outlines how roles and permissions were previously implemented in the system before shifting to the parent system.

## Database Structure

### EmployeeRole Model (`app/Models/EmployeeRole.php`)
- **Table**: `employee_roles`
- **Key Fields**:
  - `id` (bigInteger, non-incrementing)
  - `name` (string, 100 chars)
  - `modules` (text, JSON encoded array of module names)
  - `status` (boolean)
  - `restaurant_id` (unsignedBigInteger, nullable)
  - `created_at`, `updated_at`
  - `is_pushed` (for sync purposes)

### Employee Model (`app/Models/VendorEmployee.php`)
- **Table**: `users` (shared table)
- **Key Fields**:
  - `employee_role_id` - Foreign key to `employee_roles.id`
  - Relationship: `belongsTo(EmployeeRole::class, 'employee_role_id')`

## Permission Storage Format

### Modules Field (JSON)
The `modules` field in `employee_roles` table stores a JSON-encoded array of module names:
```php
// Example: ["food", "order", "kitchen_orders", "restaurant_setup"]
$role->modules = json_encode($request['modules']);
```

### Available Modules (from create.blade.php)
The system supports the following modules:
1. `food` - Food management
2. `order` - Order management
3. `kitchen_orders` - Kitchen orders
4. `restaurant_setup` - Restaurant setup
5. `addon` - Addon management
6. `wallet` - Wallet management
7. `employee` - Employee management
8. `my_shop` - My shop
9. `chat` - Chat functionality
10. `campaign` - Campaign management
11. `reviews` - Reviews
12. `pos` - POS system
13. `subscription` - Subscription (conditional)
14. `coupon` - Coupon management
15. `report` - Reports
16. `custom_role` - Custom role management
17. `options_list` - Options list
18. `shift_session` - Shift session
19. `printer_settings` - Printer settings

## Permission Checking Mechanism

### Helper Functions (`app/CentralLogics/helpers.php`)

#### 1. `employee_module_permission_check($mod_name)`
**Location**: Lines 2285-2311

**Logic**:
- For **vendor** users: Returns `true` for most modules, but checks restaurant-specific settings for:
  - `reviews`: Checks `restaurant->reviews_section`
  - `deliveryman`: Checks `restaurant->self_delivery_system`
  - `pos`: Checks `restaurant->pos_system`
  
- For **vendor_employee** users:
  ```php
  $permission = auth('vendor_employee')->user()->role->modules;
  if (isset($permission) && in_array($mod_name, (array) json_decode($permission)) == true) {
      // Additional restaurant-specific checks for reviews, deliveryman, pos
      return true;
  }
  return false;
  ```

#### 2. `module_permission_check($mod_name)` (for admin)
**Location**: Lines 2264-2283

**Logic**:
- Checks if admin has a role
- Special case: `zone` module is disabled if admin has `zone_id`
- Checks if module exists in `role->modules` JSON array
- Admin with `role_id == 1` has full access (super admin)

## Middleware Implementation

### ModulePermissionMiddleware (`app/Http/Middleware/ModulePermissionMiddleware.php`)
**Registered as**: `'module'` in `app/Http/Kernel.php`

**Usage in Routes**:
```php
Route::group(['middleware' => ['module:food']], function () {
    // Routes protected by 'food' module permission
});
```

**Logic**:
1. For admin users: Calls `Helpers::module_permission_check($module)`
2. For vendor/vendor_employee: Calls `Helpers::employee_module_permission_check($module)`
3. If permission denied: Shows error toast and redirects back

## Role Management (Previous Implementation)

### CustomRoleController (`app/Http/Controllers/Vendor/CustomRoleController.php`)

#### Key Methods:

1. **`create()`** - Lists all roles for the restaurant
   - Filters by `restaurant_id`
   - Supports search by role name
   - Paginated results

2. **`store()`** - Creates new role
   - Validates: `name` (unique per restaurant), `modules` (at least 1)
   - Stores role with:
     - `name` (from default language)
     - `modules` (JSON encoded array)
     - `restaurant_id` (from `Helpers::get_restaurant_id()`)
     - `status = 1`
   - Handles translations for multi-language support

3. **`edit($id)`** - Shows edit form
   - Loads role with translations
   - Filters by `restaurant_id`

4. **`update($id)`** - Updates role
   - Same validation as store
   - Updates `name` and `modules` (JSON encoded)
   - Updates translations

5. **`distroy($id)`** - Deletes role
   - Deletes role and its translations
   - Filters by `restaurant_id`

## Employee-Role Assignment

### EmployeeController (`app/Http/Controllers/Vendor/EmployeeController.php`)

**Key Points**:
- Employees are assigned roles via `employee_role_id` field
- When creating/updating employee:
  ```php
  $vendor->employee_role_id = $request->role_id;
  ```
- Role selection dropdown populated from:
  ```php
  $rls = EmployeeRole::where('restaurant_id', Helpers::get_restaurant_id())->get();
  ```

## Route Protection Examples

From `routes/vendor.php`:
```php
// Food module
Route::group(['prefix' => 'food', 'middleware' => ['module:food', 'subscription:food']], function () {
    // Food-related routes
});

// Order module
Route::group(['prefix' => 'order', 'middleware' => ['module:order']], function () {
    // Order-related routes
});

// Custom role module
Route::group(['prefix' => 'custom-role', 'middleware' => ['module:custom_role', 'subscription:custom_role']], function () {
    Route::get('create', 'CustomRoleController@create');
    Route::post('create', 'CustomRoleController@store');
    // ...
});
```

## Data Flow

1. **Role Creation**:
   - User selects modules via checkboxes in form
   - Form submits array: `modules[] = ['food', 'order', ...]`
   - Controller stores as JSON: `json_encode($request['modules'])`

2. **Permission Check**:
   - Route protected by `module:food` middleware
   - Middleware calls `Helpers::employee_module_permission_check('food')`
   - Helper decodes JSON: `json_decode($role->modules)`
   - Checks if module exists in array: `in_array('food', $modules)`

3. **Employee Access**:
   - Employee logs in
   - Employee has `employee_role_id` pointing to a role
   - Role has `modules` JSON array
   - When accessing protected route, system checks if module is in employee's role modules

## Key Integration Points for Parent System

When integrating with the parent system, you'll need to ensure:

1. **Data Structure Compatibility**:
   - Parent system should maintain `modules` as JSON array
   - `restaurant_id` filtering should be preserved
   - Role-employee relationship via `employee_role_id` should remain

2. **Permission Checking**:
   - `employee_module_permission_check()` function should still work
   - May need to fetch role from parent system instead of local DB
   - Consider caching for performance

3. **Route Protection**:
   - Middleware `module:*` should continue to work
   - No changes needed to route definitions

4. **Employee Assignment**:
   - Employee creation/update should reference roles from parent system
   - Role dropdown should fetch from parent system

## Special Considerations

1. **Restaurant-Specific Settings**:
   - Some modules (reviews, deliveryman, pos) have additional restaurant-level checks
   - These are separate from role permissions

2. **Vendor vs Vendor Employee**:
   - Vendors have full access (except restaurant-specific restrictions)
   - Vendor employees are restricted by their role's modules

3. **Multi-language Support**:
   - Role names are translatable via `Translation` model
   - Uses morphMany relationship

4. **Sync Mechanism**:
   - `SyncEmployeesJob` syncs roles from parent system
   - Uses `is_pushed` flag to track sync status
   - Syncs to Oracle database connection

