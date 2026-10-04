<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Interfaces\Controllers\ReportController;
use Modules\Reports\Interfaces\Controllers\PurchaseReportController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

// ============================================
// Reports - Dashboard de Ventas
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

    // ============================================
    // Reports - Dashboard de Compras
    // ============================================
    Route::get('/reports/purchases/kpis', [PurchaseReportController::class, 'kpis'])
        ->name('reports.purchases.kpis');

    Route::get('/reports/purchases/by-document-type', [PurchaseReportController::class, 'byDocumentType'])
        ->name('reports.purchases.by-document-type');

    Route::get('/reports/purchases/top-suppliers', [PurchaseReportController::class, 'topSuppliers'])
        ->name('reports.purchases.top-suppliers');

    Route::get('/reports/purchases/top-ingredients', [PurchaseReportController::class, 'topIngredients'])
        ->name('reports.purchases.top-ingredients');
});
