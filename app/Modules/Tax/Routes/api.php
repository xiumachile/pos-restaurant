<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Interfaces\Controllers\TaxController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\CheckRole;

Route::prefix('v1/taxes')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // Rutas de lectura: cualquier usuario autenticado
    Route::get('/', [TaxController::class, 'index'])
        ->name('taxes.index');
    Route::get('/{uuid}', [TaxController::class, 'show'])
        ->name('taxes.show');

    // Rutas de escritura: solo manager/admin (defensa en profundidad)
    Route::middleware([CheckRole::class . ':manager,admin'])->group(function () {
        Route::post('/', [TaxController::class, 'store'])
            ->name('taxes.store');
        Route::patch('/{uuid}', [TaxController::class, 'update'])
            ->name('taxes.update');
        Route::delete('/{uuid}', [TaxController::class, 'destroy'])
            ->name('taxes.destroy');
        Route::post('/{uuid}/mark-default', [TaxController::class, 'markDefault'])
            ->name('taxes.mark-default');
    });
});
