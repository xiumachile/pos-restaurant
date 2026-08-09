<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Interfaces\Controllers\ComboSubstitutionController;

Route::prefix('v1/catalog')->group(function () {
    Route::post('/combos/check-substitution', [ComboSubstitutionController::class, 'check'])
        ->name('catalog.combos.check-substitution');
});
