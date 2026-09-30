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
    return response()->json([
        'data' => \Modules\Branches\Domain\Entities\Branch::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
    ]);
});
