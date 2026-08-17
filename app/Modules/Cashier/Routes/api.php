<?php

use Illuminate\Support\Facades\Route;
use Modules\Cashier\Interfaces\Controllers\CashRegisterController;
use Modules\Cashier\Interfaces\Controllers\CashMovementController;
use Modules\Cashier\Interfaces\Controllers\CashCountController;
use Modules\Cashier\Interfaces\Controllers\CashierDashboardController;
use Modules\Cashier\Interfaces\Controllers\CashierTablesController;
use Modules\Cashier\Interfaces\Controllers\CashierReportController;
use Modules\Cashier\Interfaces\Controllers\TipPolicyController;
use App\Shared\Http\Middleware\TenantContextMiddleware;

// Rutas de Caja que NO necesitan middleware idempotent
Route::prefix('v1/cashier')->middleware(['auth:api', TenantContextMiddleware::class])->group(function () {
    // Dashboard
    Route::get('/dashboard', [CashierDashboardController::class, 'index'])
        ->name('cashier.dashboard');

    Route::get('/session-payments', [CashierDashboardController::class, 'sessionPayments'])
        ->name('cashier.dashboard');

    // ============================================
    // Gestión de cuentas por mesa (cobro por mesa)
    // ============================================
    Route::get('/tables-with-bills', [CashierTablesController::class, 'tablesWithBills'])
        ->name('cashier.tables-with-bills');
    
    // POST sin middleware idempotent (usa idempotency_key propia por cada order)
    Route::post('/tables/{tableUuid}/charge', [CashierTablesController::class, 'chargeTable'])
        ->name('cashier.tables.charge');

    Route::post('/bills/{billUuid}/pay', [CashierTablesController::class, 'payBill'])
        ->name('cashier.bills.pay');

    // ============================================
    // Reportes de caja
    // ============================================
    Route::get('/reports/x-report', [CashierReportController::class, 'xReport'])
        ->name('cashier.reports.x-report');

    Route::get('/reports/z-report/{uuid}', [CashierReportController::class, 'zReport'])
        ->name('cashier.reports.z-report');

    Route::get('/sessions/history', [CashierReportController::class, 'history'])
        ->name('cashier.sessions.history');

    // ============================================
    // Configuración de políticas de propinas
    // ============================================
    Route::get('/tip-policy', [TipPolicyController::class, 'show'])
        ->name('cashier.tip-policy.show');

    Route::put('/tip-policy', [TipPolicyController::class, 'update'])
        ->name('cashier.tip-policy.update');
});

// Rutas que SÍ necesitan idempotencia
Route::prefix('v1/cashier')->middleware(['auth:api', TenantContextMiddleware::class, 'idempotent'])->group(function () {
    // Cash Registers
    Route::get('/registers', [CashRegisterController::class, 'index'])
        ->name('cashier.registers.index');
    Route::post('/registers', [CashRegisterController::class, 'store'])
        ->name('cashier.registers.store');
    Route::get('/registers/{uuid}', [CashRegisterController::class, 'show'])
        ->name('cashier.registers.show');
    Route::patch('/registers/{uuid}/toggle-active', [CashRegisterController::class, 'toggleActive'])
        ->name('cashier.registers.toggle-active');

    // Cash Movements
    Route::get('/movements', [CashMovementController::class, 'index'])
        ->name('cashier.movements.index');
    Route::post('/movements', [CashMovementController::class, 'store'])
        ->name('cashier.movements.store');
    Route::get('/movements/summary', [CashMovementController::class, 'summary'])
        ->name('cashier.movements.summary');

    // Cash Counts (Arqueos)
    Route::get('/counts', [CashCountController::class, 'index'])
        ->name('cashier.counts.index');
    Route::post('/counts', [CashCountController::class, 'store'])
        ->name('cashier.counts.store');
    Route::get('/counts/{uuid}', [CashCountController::class, 'show'])
        ->name('cashier.counts.show');
    Route::post('/counts/{uuid}/supervise', [CashCountController::class, 'supervise'])
        ->name('cashier.counts.supervise');
});
