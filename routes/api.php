<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Las rutas de los módulos se cargan desde ModuleServiceProvider.
| Broadcasting se configura vía withBroadcasting() en bootstrap/app.php.
*/

// ============================================================================
// Rutas públicas para setup inicial (Login POS)
// ============================================================================
Route::get('/v1/public/branches', function () {
    // Desactivamos los scopes globales de tenant porque esta es una ruta pública pre-login.
    // Solo devolvemos id, name y code de sucursales activas, sin datos sensibles.
    return response()->json([
        'data' => \Modules\Branches\Domain\Entities\Branch::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
    ]);
});

// ═══════════════════════════════════════════
// Customers (CRM básico)
// ═══════════════════════════════════════════
Route::middleware(['auth:api', \App\Shared\Http\Middleware\TenantContextMiddleware::class])->prefix('v1')->group(function () {
    Route::get('/customers/search', [\Modules\Customers\Interfaces\Controllers\CustomerController::class, 'search']);
    Route::apiResource('customers', \Modules\Customers\Interfaces\Controllers\CustomerController::class);
});

// HALLAZGO C-03: Rutas para gestión de trabajos de impresión (Tauri Client)
Route::middleware(['auth:api'])->group(function () {
});
