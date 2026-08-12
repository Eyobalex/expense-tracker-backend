<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api-json'])
    ->group(function (): void {
        Route::prefix('auth')->middleware('throttle:auth-api')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->middleware('idempotency');
            Route::post('login', [AuthController::class, 'login'])->middleware('idempotency');
            Route::post('forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('idempotency');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('idempotency');
        });

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
            Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('idempotency');
            Route::post('auth/refresh-or-revoke', [AuthController::class, 'refreshOrRevoke'])->middleware('idempotency');
            Route::get('me', [ProfileController::class, 'show']);
            Route::patch('me', [ProfileController::class, 'update'])->middleware('idempotency');
            Route::get('devices', [DeviceController::class, 'index']);
            Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('idempotency');
        });
    });
