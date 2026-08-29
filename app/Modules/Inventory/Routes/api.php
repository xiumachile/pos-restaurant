<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Interfaces\Controllers\InventoryController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/inventory')
    ->middleware(['auth:api', TenantContextMiddleware::class, 'capability:can_manage_inventory'])
    ->group(function () {
        // ============================================
        // Inventario CRUD
        // ============================================
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
        Route::get('/{uuid}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::put('/{uuid}', [InventoryController::class, 'update'])->name('inventory.update');
        
        // ============================================
        // Movimientos de stock
        // ============================================
        Route::post('/{uuid}/movement', [InventoryController::class, 'movement'])->name('inventory.movement');
        Route::get('/{uuid}/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    });
