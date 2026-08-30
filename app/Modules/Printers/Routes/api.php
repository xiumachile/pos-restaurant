<?php

use Illuminate\Support\Facades\Route;
use Modules\Printers\Interfaces\Controllers\PrinterController;
use Modules\Printers\Interfaces\Controllers\PrinterStationMappingController;
use Modules\Printers\Interfaces\Controllers\PrintJobController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1')
    ->middleware(['auth:api', TenantContextMiddleware::class, 'capability:can_print_receipts'])
    ->group(function () {
        // ============================================
        // Printers (CRUD)
        // ============================================
        Route::get('/printers', [PrinterController::class, 'index'])->name('printers.index');
        Route::post('/printers', [PrinterController::class, 'store'])->name('printers.store');
        Route::get('/printers/{uuid}', [PrinterController::class, 'show'])->name('printers.show');
        Route::put('/printers/{uuid}', [PrinterController::class, 'update'])->name('printers.update');
        Route::delete('/printers/{uuid}', [PrinterController::class, 'destroy'])->name('printers.destroy');
        Route::post('/printers/{uuid}/test', [PrinterController::class, 'test'])->name('printers.test');

        // ============================================
        // Printer Station Mappings
        // ============================================
        Route::get('/printers/mappings', [PrinterStationMappingController::class, 'index'])->name('printers.mappings.index');
        Route::post('/printers/mappings', [PrinterStationMappingController::class, 'store'])->name('printers.mappings.store');
        Route::delete('/printers/mappings/{uuid}', [PrinterStationMappingController::class, 'destroy'])->name('printers.mappings.destroy');

        // ============================================
        // Print Jobs
        // ============================================
        Route::get('/print-jobs', [PrintJobController::class, 'index'])->name('print-jobs.index');
        Route::get('/print-jobs/{uuid}', [PrintJobController::class, 'show'])->name('print-jobs.show');
        
        // Endpoints para POS local (Tauri) - arquitectura híbrida
        Route::post('/print-jobs/{uuid}/claim', [PrintJobController::class, 'claim'])->name('print-jobs.claim');
        Route::post('/print-jobs/{uuid}/complete', [PrintJobController::class, 'complete'])->name('print-jobs.complete');
        Route::post('/print-jobs/{uuid}/fail', [PrintJobController::class, 'fail'])->name('print-jobs.fail');
        
        // Endpoints legacy (mantener para compatibilidad)
        Route::post('/print-jobs/{uuid}/retry', [PrintJobController::class, 'retry'])->name('print-jobs.retry');
        Route::post('/print-jobs/process', [PrintJobController::class, 'process'])->name('print-jobs.process');
    });
