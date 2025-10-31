<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // General API endpoints with standard rate limit (60/min)
    Route::middleware('throttle:api')->group(function () {
        // User can only update their own profile - no ID in URL
        Route::put('/me', [UserController::class, 'update']);
    });

    // Admin-only endpoint with specific rate limit (30/min)
    Route::put('/users/{id}/balance', [UserController::class, 'updateBalance'])
        ->middleware('admin');

    // User transfers with specific rate limit (10/min)
    Route::middleware('transfers')->group(function () {
        Route::post('/me/transfers/initiate', [UserController::class, 'initiateTransfer']);
        Route::post('/me/transfers/confirm', [UserController::class, 'confirmTransfer']);
    });
});
