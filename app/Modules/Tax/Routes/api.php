<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Interfaces\Controllers\TaxController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/taxes')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    Route::get('/', [TaxController::class, 'index'])
        ->name('taxes.index');
    Route::post('/', [TaxController::class, 'store'])
        ->name('taxes.store');
    Route::get('/{uuid}', [TaxController::class, 'show'])
        ->name('taxes.show');
    Route::patch('/{uuid}', [TaxController::class, 'update'])
        ->name('taxes.update');
    Route::delete('/{uuid}', [TaxController::class, 'destroy'])
        ->name('taxes.destroy');
    Route::post('/{uuid}/mark-default', [TaxController::class, 'markDefault'])
        ->name('taxes.mark-default');
});
