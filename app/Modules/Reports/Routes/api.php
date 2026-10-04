<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Interfaces\Controllers\ReportController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

// ============================================
// Reports - Dashboard y métricas para el dueño
// ============================================
Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])
        ->name('reports.dashboard');

    Route::get('/reports/top-products', [ReportController::class, 'topProducts'])
        ->name('reports.top-products');

    Route::get('/reports/sales-by-hour', [ReportController::class, 'salesByHour'])
        ->name('reports.sales-by-hour');

    Route::get('/reports/payment-methods', [ReportController::class, 'paymentMethods'])
        ->name('reports.payment-methods');
});
