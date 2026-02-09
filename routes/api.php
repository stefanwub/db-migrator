<?php

use App\Http\Controllers\Api\DbCopyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('db-copies')
    ->group(function (): void {
        Route::post('/', [DbCopyController::class, 'store'])
            ->name('api.db-copies.store');

        Route::get('{dbCopy}', [DbCopyController::class, 'show'])
            ->name('api.db-copies.show');
    });

