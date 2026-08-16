<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Interfaces\Controllers\BillController;
use Modules\Payments\Interfaces\Controllers\CashSessionController;
use Modules\Payments\Interfaces\Controllers\PaymentController;
use Modules\Payments\Interfaces\Controllers\PaymentMethodController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // ============================================
    // Payment Methods
    // ============================================
    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])
        ->name('payment-methods.index');

    // ============================================
    // Payments
    // ============================================
    Route::post('/billing/payments', [PaymentController::class, 'store'])
        ->name('payments.store');

    // ============================================
    // Split Bill
    // ============================================
    Route::post('/orders/{uuid}/split', [BillController::class, 'split'])
        ->name('orders.split');
    Route::get('/orders/{uuid}/bills', [BillController::class, 'index'])
        ->name('orders.bills');

    // ============================================
    // Cash Sessions
    // ============================================
    Route::post('/cash-sessions/open', [CashSessionController::class, 'open'])
        ->name('cash-sessions.open');
    Route::post('/cash-sessions/{uuid}/close', [CashSessionController::class, 'close'])
        ->name('cash-sessions.close');
    Route::get('/cash-sessions/current', [CashSessionController::class, 'current'])
        ->name('cash-sessions.current');
});
