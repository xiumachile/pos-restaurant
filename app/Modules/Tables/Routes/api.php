<?php

use Illuminate\Support\Facades\Route;
use Modules\Tables\Interfaces\Controllers\DiningZoneController;
use Modules\Tables\Interfaces\Controllers\FloorPlanController;
use Modules\Tables\Interfaces\Controllers\RestaurantTableController;

Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    // Mesas (CRUD básico existente)
    Route::apiResource('tables', RestaurantTableController::class)
        ->only(['index', 'store', 'update']);

    Route::get('tables/{uuid}/orders', [RestaurantTableController::class, 'orders'])
        ->name('tables.orders');
    Route::put('tables/{table}/status', [RestaurantTableController::class, 'updateStatus'])
        ->name('tables.update-status');

    // Floor Plan (Fase 4.1)
    Route::get('floor-plan', [FloorPlanController::class, 'show'])
        ->name('floor-plan.show');
    Route::put('floor-plan', [FloorPlanController::class, 'update'])
        ->name('floor-plan.update');

    // Zonas del restaurante (Fase 4.1)
    Route::post('dining-zones', [DiningZoneController::class, 'store'])
        ->name('dining-zones.store');
    Route::patch('dining-zones/{uuid}', [DiningZoneController::class, 'update'])
        ->name('dining-zones.update');
    Route::delete('dining-zones/{uuid}', [DiningZoneController::class, 'destroy'])
        ->name('dining-zones.destroy');
});
