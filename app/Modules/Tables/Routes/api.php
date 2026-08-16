<?php

use Illuminate\Support\Facades\Route;
use Modules\Tables\Interfaces\Controllers\RestaurantTableController;

Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::apiResource('tables', RestaurantTableController::class)
        ->only(['index', 'store', 'update']);

    Route::get('tables/{uuid}/orders', [RestaurantTableController::class, 'orders'])
        ->name('tables.orders');
    Route::put('tables/{table}/status', [RestaurantTableController::class, 'updateStatus'])
        ->name('tables.update-status');
});
