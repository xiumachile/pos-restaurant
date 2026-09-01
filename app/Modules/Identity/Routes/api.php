<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Interfaces\Controllers\AuthController;

Route::prefix('v1/auth')->group(function () {
    // Rutas públicas (sin autenticación)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('/login/pos', [AuthController::class, 'posLogin'])
        ->middleware('throttle:3,1')
        ->name('auth.login.pos');

    // NUEVO: Crear sesión POS efímera (setup, O(n) aceptable)
    Route::post('/pos-session', [AuthController::class, 'posSession'])
        ->middleware('throttle:3,1')
        ->name('auth.pos-session');

    // Rutas protegidas (requieren JWT válido)
    Route::middleware('auth:api')->group(function () {
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->name('auth.refresh');

        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('auth.me');
    });
});
