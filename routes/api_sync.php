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
});

