<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Interfaces\Controllers\CashierController;
use Modules\Orders\Interfaces\Controllers\OrderController;
use Modules\Orders\Interfaces\Controllers\OrderItemController;
use Modules\Orders\Interfaces\Controllers\OrderTransitionController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\IdempotencyKeyMiddleware;

// ============================================
// Orders - Lecturas (SIN idempotencia)
// ============================================
Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{uuid}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/cashier/active', [CashierController::class, 'active'])->name('cashier.active');
});

// ============================================
// Orders - Mutaciones (CON idempotencia)
// Principio arquitectónico #7: idempotencia en mutaciones críticas
// ============================================
Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class, 'idempotent'])->group(function () {
    
    // Orders CRUD
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::put('/orders/{uuid}', [OrderController::class, 'update'])->name('orders.update');
    Route::delete('/orders/{uuid}', [OrderController::class, 'destroy'])->name('orders.destroy');

    // Order Items
    Route::post('/orders/{orderUuid}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
    Route::delete('/orders/{orderUuid}/items/{itemUuid}', [OrderItemController::class, 'destroy'])->name('orders.items.destroy');

    // Transiciones de estado
    Route::post('/orders/{uuid}/confirm', [OrderTransitionController::class, 'confirm'])->name('orders.confirm');
    Route::post('/orders/{uuid}/prepare', [OrderTransitionController::class, 'prepare'])->name('orders.prepare');
    Route::post('/orders/{uuid}/ready', [OrderTransitionController::class, 'ready'])->name('orders.ready');
    Route::post('/orders/{uuid}/serve', [OrderTransitionController::class, 'serve'])->name('orders.serve');
    Route::post('/orders/{uuid}/pay', [OrderTransitionController::class, 'pay'])->name('orders.pay');
    Route::post('/orders/{uuid}/close', [OrderTransitionController::class, 'close'])->name('orders.close');
    Route::post('/orders/{uuid}/cancel', [OrderTransitionController::class, 'cancel'])->name('orders.cancel');
});
