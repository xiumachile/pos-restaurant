<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Interfaces\Controllers\ComboSubstitutionController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/catalog')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    Route::post('/combos/check-substitution', [ComboSubstitutionController::class, 'check'])
        ->name('catalog.combos.check-substitution');
});
