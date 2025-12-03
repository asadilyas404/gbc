<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataPushController;
use App\Http\Controllers\DataReceiveController;
use App\Http\Controllers\Vendor\ShiftSessionController;

// Health check endpoint for connectivity verification
Route::get('/health-check', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()], 200);
});

Route::post('/v1/push-sale-invoices', [App\Http\Controllers\DataPushController::class, 'pushInvoices']);
Route::post('/receive-data', [DataReceiveController::class, 'receive']);
Route::post('accounts/post-cash-adjustment', [ShiftSessionController::class, 'postCashAdjustment'])->name('accounts.post-cash-adjustment');
Route::post('accounts/remove-cash-adjustment', [ShiftSessionController::class, 'removeCashAdjustment'])->name('accounts.remove-cash-adjustment');
