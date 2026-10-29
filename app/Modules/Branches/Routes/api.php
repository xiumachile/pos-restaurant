<?php

use Illuminate\Support\Facades\Route;
use Modules\Branches\Interfaces\Controllers\BranchSettingsController;

Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::patch('branch/settings', [BranchSettingsController::class, 'update']);
});
