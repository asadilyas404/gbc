<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataPushController;
use App\Http\Controllers\DataReceiveController;


Route::post('/v1/push-sale-invoices', [App\Http\Controllers\DataPushController::class, 'pushInvoices']);
Route::post('/receive-data', [DataReceiveController::class, 'receive']);
