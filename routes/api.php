<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataPushController;


Route::post('/v1/push-sale-invoices', [App\Http\Controllers\DataPushController::class, 'pushInvoices']);
