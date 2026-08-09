<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api automatically by Laravel.
| Version 1 routes are grouped under /api/v1.
|
| Authentication: Bearer token via Laravel Sanctum.
| Header: Authorization: Bearer <token>
|
*/

// ─── V1 ─────────────────────────────────────────────────────────────────────

Route::prefix('v1')->group(function () {

    // Public (no auth required)
    Route::post('/auth/login', [V1\AuthController::class, 'login']);

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'tenant', 'licensed', 'token.tenant'])->group(function () {

        // Auth
        Route::post('/auth/logout', [V1\AuthController::class, 'logout']);
        Route::get('/auth/me',      [V1\AuthController::class, 'me']);

        // Employee profile (any authenticated user)
        Route::get('/employee/profile', [V1\EmployeeController::class, 'profile']);

        // DTR (employee)
        Route::get('/dtr',         [V1\DTRController::class, 'index']);
        Route::post('/dtr/submit', [V1\DTRController::class, 'submit']);

        // Leaves (employee)
        Route::get('/leaves',          [V1\LeaveController::class, 'index']);
        Route::get('/leaves/credits',  [V1\LeaveController::class, 'credits']);
        Route::post('/leaves',         [V1\LeaveController::class, 'store']);
        Route::delete('/leaves/{leave}', [V1\LeaveController::class, 'destroy']);

        // Gate Passes (employee)
        Route::get('/gate-passes',             [V1\GatePassController::class, 'index']);
        Route::post('/gate-passes',            [V1\GatePassController::class, 'store']);
        Route::delete('/gate-passes/{gatePass}', [V1\GatePassController::class, 'destroy']);

        // HR-only routes
        Route::middleware('hr')->group(function () {
            Route::get('/employees', [V1\EmployeeController::class, 'index']);
        });

        // On-premise sync agent.
        //
        // For devices that only speak the polling protocol and therefore sit on a
        // tenant's private network, unreachable from this server. The agent polls
        // locally and posts here. The token's ability restricts it to this task, and
        // the tenant is taken from the token rather than the request body.
        Route::middleware(['ability:biometric:ingest', 'throttle:60,1'])->group(function () {
            Route::get('/biometric/devices',  [V1\BiometricIngestController::class, 'devices']);
            Route::post('/biometric/punches', [V1\BiometricIngestController::class, 'store']);
        });
    });
});
