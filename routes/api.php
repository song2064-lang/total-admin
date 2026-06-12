<?php

use App\Http\Controllers\Api\OrderLookupController;
use App\Http\Controllers\Api\OrderReceiveController;
use Illuminate\Support\Facades\Route;

Route::middleware('channel.hmac')->group(function () {
    Route::post('/orders', OrderReceiveController::class);
    Route::get('/orders/{channelOrderNo}', OrderLookupController::class);
});
