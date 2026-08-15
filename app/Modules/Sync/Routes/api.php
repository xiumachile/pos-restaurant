<?php

use Illuminate\Support\Facades\Route;
use Modules\Sync\Interfaces\Controllers\SyncController;
use App\Shared\Http\Middleware\TenantContextMiddleware;
use App\Shared\Http\Middleware\CheckRole;

Route::prefix('v1/sync')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // Health check (cualquier usuario autenticado)
    Route::get('/health', [SyncController::class, 'health'])
        ->name('sync.health');

    // Status (cualquier usuario autenticado)
    Route::get('/status', [SyncController::class, 'status'])
        ->name('sync.status');

    // Push y Pull (waiter, manager, admin)
    Route::middleware([CheckRole::class . ':waiter,manager,admin'])->group(function () {
        Route::post('/push', [SyncController::class, 'push'])
            ->name('sync.push');
        Route::post('/pull', [SyncController::class, 'pull'])
            ->name('sync.pull');
    });
});
