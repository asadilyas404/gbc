<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderSyncController;

/*
|--------------------------------------------------------------------------
| Sync API Routes
|--------------------------------------------------------------------------
|
| These routes are for syncing data from local/staging server to live server
| Protected by API token authentication middleware
|
*/

Route::middleware('validate.api.token')->group(function () {
    // Orders: Local → Live (PUSH)
    Route::post('/orders/sync-bulk', [OrderSyncController::class, 'syncBulk']);
    Route::post('/orders/sync-complete', [OrderSyncController::class, 'syncComplete']);

    // Customers: Live → Local (PULL)
    Route::get('/customers/get-data', [\App\Http\Controllers\Api\CustomerSyncController::class, 'getData']);
    Route::post('/customers/mark-pushed', [\App\Http\Controllers\Api\CustomerSyncController::class, 'markAsPushed']);

    // Food: Live → Local (PULL)
    Route::get('/food/get-data', [\App\Http\Controllers\Api\FoodSyncController::class, 'getFoodData']);
    Route::post('/food/mark-pushed', [\App\Http\Controllers\Api\FoodSyncController::class, 'markAsPushed']);

    // Branches/Restaurants: Live → Local (PULL)
    Route::get('/branches-restaurants/get-data', [\App\Http\Controllers\Api\BranchRestaurantSyncController::class, 'getData']);
    Route::post('/branches-restaurants/mark-pushed', [\App\Http\Controllers\Api\BranchRestaurantSyncController::class, 'markAsPushed']);

    // Employees/Users: Live → Local (PULL)
    Route::get('/employees-users/get-data', [\App\Http\Controllers\Api\EmployeeUserSyncController::class, 'getData']);
    Route::post('/employees-users/mark-pushed', [\App\Http\Controllers\Api\EmployeeUserSyncController::class, 'markAsPushed']);
});

