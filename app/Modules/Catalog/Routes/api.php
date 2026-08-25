<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Interfaces\Controllers\CategoryController;
use Modules\Catalog\Interfaces\Controllers\ProductController;
use Modules\Catalog\Interfaces\Controllers\PriceListController;
use Modules\Catalog\Interfaces\Controllers\ProductPriceController;
use Modules\Catalog\Interfaces\Controllers\MenuController;
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

    
    // Cartas/Menús — lectura para todos los usuarios autenticados
    // (los meseros necesitan GET /menus/active para cargar la carta automáticamente)
    // IMPORTANTE: /menus/active debe ir ANTES de /menus/{uuid} para evitar colisión
    Route::get('/menus', [MenuController::class, 'index'])->name('catalog.menus.index');
    Route::get('/menus/active', [MenuController::class, 'active'])->name('catalog.menus.active');
    Route::get('/menus/{uuid}', [MenuController::class, 'show'])->name('catalog.menus.show');

    // Cartas/Menús — escritura solo admin/manager
    Route::middleware([CheckRole::class . ':admin,manager'])->group(function () {
        Route::post('/menus', [MenuController::class, 'store'])->name('catalog.menus.store');
        Route::put('/menus/{uuid}', [MenuController::class, 'update'])->name('catalog.menus.update');
        Route::delete('/menus/{uuid}', [MenuController::class, 'destroy'])->name('catalog.menus.destroy');
        Route::put('/menus/{uuid}/activations', [MenuController::class, 'upsertActivations'])->name('catalog.menus.activations.upsert');
        Route::put('/menus/{uuid}/products', [MenuController::class, 'assignProducts'])->name('catalog.menus.products.assign');
    });

    // Listas de precios (lectura para usuarios autenticados)
    Route::get('/price-lists', [PriceListController::class, 'index'])->name('catalog.price-lists.index');

    // Precios de un producto (lectura)
    Route::get('/products/{uuid}/prices', [ProductPriceController::class, 'index'])->name('catalog.products.prices.index');

    // Listas de precios y precios de productos (escritura solo admin/manager)
    Route::middleware([CheckRole::class . ':admin,manager'])->group(function () {
        Route::post('/price-lists', [PriceListController::class, 'store'])->name('catalog.price-lists.store');
        Route::put('/price-lists/{uuid}', [PriceListController::class, 'update'])->name('catalog.price-lists.update');
        Route::delete('/price-lists/{uuid}', [PriceListController::class, 'destroy'])->name('catalog.price-lists.destroy');

        Route::put('/products/{uuid}/prices', [ProductPriceController::class, 'upsert'])->name('catalog.products.prices.upsert');
        Route::delete('/products/{productUuid}/prices/{priceListUuid}', [ProductPriceController::class, 'destroy'])->name('catalog.products.prices.destroy');
    });
});
