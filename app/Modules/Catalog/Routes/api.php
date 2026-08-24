<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Interfaces\Controllers\CategoryController;
use Modules\Catalog\Interfaces\Controllers\ProductController;
use Modules\Catalog\Interfaces\Controllers\ComboSubstitutionController;
use Modules\Catalog\Interfaces\Controllers\ComboReplacementRuleController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\CheckRole;

Route::prefix('v1/catalog')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // Categorías (lectura pública para usuarios autenticados)
    Route::get('/categories', [CategoryController::class, 'index'])->name('catalog.categories.index');
    Route::get('/categories/{uuid}', [CategoryController::class, 'show'])->name('catalog.categories.show');

    // Categorías (escritura solo admin/manager)
    Route::middleware([CheckRole::class . ':admin,manager'])->group(function () {
        Route::post('/categories', [CategoryController::class, 'store'])->name('catalog.categories.store');
        Route::put('/categories/{uuid}', [CategoryController::class, 'update'])->name('catalog.categories.update');
        Route::delete('/categories/{uuid}', [CategoryController::class, 'destroy'])->name('catalog.categories.destroy');
    });

    // Productos (lectura pública para usuarios autenticados)
    Route::get('/products', [ProductController::class, 'index'])->name('catalog.products.index');
    Route::get('/products/{uuid}', [ProductController::class, 'show'])->name('catalog.products.show');

    // Productos (escritura solo admin/manager)
    Route::middleware([CheckRole::class . ':admin,manager'])->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('catalog.products.store');
        Route::put('/products/{uuid}', [ProductController::class, 'update'])->name('catalog.products.update');
        Route::delete('/products/{uuid}', [ProductController::class, 'destroy'])->name('catalog.products.destroy');
    });

    // Sustituciones de combos (check)
    Route::post('/combos/check-substitution', [ComboSubstitutionController::class, 'check'])
        ->name('catalog.combos.check-substitution');

    // Políticas de sustitución (configuración - solo admin/manager)
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
