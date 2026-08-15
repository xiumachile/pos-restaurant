<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Interfaces\Controllers\AuditLogController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

Route::prefix('v1/audit-logs')
    ->middleware(['auth:api', TenantContextMiddleware::class, 'role:admin,manager'])
    ->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/actions', [AuditLogController::class, 'actions'])->name('audit-logs.actions');
        Route::get('/{uuid}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
