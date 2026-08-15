<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Interfaces\Controllers\ComboReplacementRuleController;
use Modules\Catalog\Interfaces\Controllers\ComboSubstitutionController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\CheckRole;

Route::prefix('v1/catalog')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // Validación de sustitución (cualquier usuario autenticado)
    Route::post('/combos/check-substitution', [ComboSubstitutionController::class, 'check'])
        ->name('catalog.combos.check-substitution');

    // Configuración de políticas de sustitución (solo admin/manager)
    Route::prefix('combos')
        ->middleware([CheckRole::class . ':admin,manager'])
        ->group(function () {
            Route::get('/{menuItemUuid}/substitution-policies', [ComboReplacementRuleController::class, 'index'])
                ->name('catalog.combos.substitution-policies.index');

            Route::put('/{menuItemUuid}/items/{productUuid}/substitution-policy', [ComboReplacementRuleController::class, 'update'])
                ->name('catalog.combos.substitution-policies.update');

            Route::delete('/{menuItemUuid}/items/{productUuid}/substitution-policy', [ComboReplacementRuleController::class, 'destroy'])
                ->name('catalog.combos.substitution-policies.destroy');
        });
});
