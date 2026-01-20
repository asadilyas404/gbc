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

    Route::post('/orders/sync-bulk', [OrderSyncController::class, 'syncBulk']);
    Route::post('/orders/sync-complete', [OrderSyncController::class, 'syncComplete']);

    Route::get('/customers/get-data', [\App\Http\Controllers\Api\CustomerSyncController::class, 'getData']);
    Route::post('/customers/update-sync-state', [\App\Http\Controllers\Api\CustomerSyncController::class, 'updateSyncState']);

    Route::get('/food/get-data', [\App\Http\Controllers\Api\FoodSyncController::class, 'getFoodData']);
    Route::post('/food/update-sync-state', [\App\Http\Controllers\Api\FoodSyncController::class, 'updateSyncState']);

    Route::get('/branches-restaurants/get-data', [\App\Http\Controllers\Api\BranchRestaurantSyncController::class, 'getData']);
    Route::post('/branches-restaurants/update-sync-state', [\App\Http\Controllers\Api\BranchRestaurantSyncController::class, 'updateSyncState']);

    Route::get('/employees-users/get-data', [\App\Http\Controllers\Api\EmployeeUserSyncController::class, 'getData']);
    Route::post('/employees-users/update-sync-state', [\App\Http\Controllers\Api\EmployeeUserSyncController::class, 'updateSyncState']);
    Route::post('/employees-users/update-password', [\App\Http\Controllers\Api\EmployeeUserSyncController::class, 'updatePassword']);
});

