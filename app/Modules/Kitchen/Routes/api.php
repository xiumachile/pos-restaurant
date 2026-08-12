<?php

use Illuminate\Support\Facades\Route;
use Modules\Kitchen\Interfaces\Controllers\KitchenController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/kitchen')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // ============================================
    // Kitchen Display System endpoints
    // ============================================
    Route::get('/queue', [KitchenController::class, 'queue'])->name('kitchen.queue');
    Route::get('/stats', [KitchenController::class, 'stats'])->name('kitchen.stats');
    Route::get('/history', [KitchenController::class, 'history'])->name('kitchen.history');
    Route::post('/orders/{uuid}/assign-cook', [KitchenController::class, 'assignCook'])->name('kitchen.assign-cook');
});
