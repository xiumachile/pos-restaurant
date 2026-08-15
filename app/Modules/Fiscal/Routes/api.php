<?php

use Illuminate\Support\Facades\Route;
use Modules\Fiscal\Interfaces\Controllers\DteDocumentController;
use Modules\Fiscal\Interfaces\Controllers\DteFolioRangeController;
use Modules\Fiscal\Interfaces\Controllers\DteCertificateController;
use Modules\Fiscal\Interfaces\Controllers\SalesBookController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\IdempotencyKeyMiddleware;

Route::prefix('v1/fiscal')->middleware(['auth:api', TenantContextMiddleware::class, 'idempotent'])->group(function () {
    // ============================================
    // DTE Documents
    // ============================================
    Route::get('/dtes', [DteDocumentController::class, 'index'])->name('fiscal.dtes.index');
    Route::post('/dtes', [DteDocumentController::class, 'store'])->name('fiscal.dtes.store');
    Route::get('/dtes/{uuid}', [DteDocumentController::class, 'show'])->name('fiscal.dtes.show');
    Route::post('/dtes/{uuid}/cancel', [DteDocumentController::class, 'cancel'])->name('fiscal.dtes.cancel');
    Route::post('/dtes/{uuid}/resend', [DteDocumentController::class, 'resend'])->name('fiscal.dtes.resend');

    // ============================================
    // Folio Ranges (CAF)
    // ============================================
    Route::get('/folios', [DteFolioRangeController::class, 'index'])->name('fiscal.folios.index');
    Route::post('/folios', [DteFolioRangeController::class, 'store'])->name('fiscal.folios.store');
    Route::get('/folios/summary', [DteFolioRangeController::class, 'summary'])->name('fiscal.folios.summary');

    // ============================================
    // Certificates
    // ============================================
    Route::get('/certificates', [DteCertificateController::class, 'index'])->name('fiscal.certificates.index');
    Route::post('/certificates', [DteCertificateController::class, 'store'])->name('fiscal.certificates.store');
    Route::delete('/certificates/{uuid}', [DteCertificateController::class, 'destroy'])->name('fiscal.certificates.destroy');

    // ============================================
    // Sales Book (Libro de Ventas)
    // ============================================
    Route::get('/sales-book', [SalesBookController::class, 'index'])->name('fiscal.sales-book.index');
    Route::get('/sales-book/csv', [SalesBookController::class, 'exportCsv'])->name('fiscal.sales-book.csv');
});
