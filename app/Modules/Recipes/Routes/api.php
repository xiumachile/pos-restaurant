<?php

use Illuminate\Support\Facades\Route;
use Modules\Recipes\Interfaces\Controllers\IngredientController;
use Modules\Recipes\Interfaces\Controllers\RecipeController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/recipes')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // ============================================
    // Ingredientes (Insumos / Materia Prima)
    // ============================================
    Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::get('/ingredients/{uuid}', [IngredientController::class, 'show'])->name('ingredients.show');
    Route::post('/ingredients/{uuid}/purchase', [IngredientController::class, 'purchase'])->name('ingredients.purchase');
    Route::get('/ingredients/{uuid}/purchases', [IngredientController::class, 'purchases'])->name('ingredients.purchases');

    // ============================================
    // Recetas (Fichas Técnicas / BOM)
    // ============================================
    Route::post('/', [RecipeController::class, 'store'])->name('recipes.store');
    Route::get('/product/{uuid}', [RecipeController::class, 'showByProduct'])->name('recipes.show-by-product');
    Route::put('/{uuid}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('/{uuid}', [RecipeController::class, 'destroy'])->name('recipes.destroy');

    // ============================================
    // Reportes
    // ============================================
    Route::get('/food-cost', [RecipeController::class, 'foodCostReport'])->name('recipes.food-cost');
});
