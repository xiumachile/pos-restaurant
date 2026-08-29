<?php

use Illuminate\Support\Facades\Route;
use Modules\Companies\Interfaces\Controllers\CompanyController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

// ============================================
// Companies - Lecturas (sin idempotencia)
// ============================================
Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index'])
        ->name('companies.index');

    Route::get('/companies/{uuid}', [CompanyController::class, 'show'])
        ->name('companies.show');

    Route::get('/companies/{uuid}/capabilities', [CompanyController::class, 'getCapabilities'])
        ->name('companies.capabilities.index');
});

// ============================================
// Companies - Mutaciones (con idempotencia para POST/PUT)
// ============================================
Route::prefix('v1')->middleware(['auth:api', TenantContextMiddleware::class, 'idempotent'])->group(function () {
    // Crear empresa: solo super_admin
    Route::post('/companies', [CompanyController::class, 'store'])
        ->middleware('role:super_admin')
        ->name('companies.store');

    // Actualizar empresa: admin o super_admin
    Route::put('/companies/{uuid}', [CompanyController::class, 'update'])
        ->middleware('role:admin,super_admin')
        ->name('companies.update');

    // Eliminar empresa (soft delete): admin o super_admin
    Route::delete('/companies/{uuid}', [CompanyController::class, 'destroy'])
        ->middleware('role:admin,super_admin')
        ->name('companies.destroy');

    // Actualizar capabilities: admin o super_admin
    Route::put('/companies/{uuid}/capabilities', [CompanyController::class, 'updateCapabilities'])
        ->middleware('role:admin,super_admin')
        ->name('companies.capabilities.update');
});
