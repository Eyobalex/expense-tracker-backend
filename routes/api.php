<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FinancialAccountController;
use App\Http\Controllers\Api\V1\OnboardingController;
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

            Route::get('onboarding', [OnboardingController::class, 'show']);
            Route::get('currencies', [CurrencyController::class, 'index']);
            Route::put('onboarding', [OnboardingController::class, 'update'])->middleware('idempotency');

            Route::get('accounts', [FinancialAccountController::class, 'index']);
            Route::post('accounts', [FinancialAccountController::class, 'store'])->middleware('idempotency');
            Route::get('accounts/{financialAccount}', [FinancialAccountController::class, 'show']);
            Route::patch('accounts/{financialAccount}', [FinancialAccountController::class, 'update'])->middleware('idempotency');
            Route::post('accounts/{financialAccount}/archive', [FinancialAccountController::class, 'archive'])->middleware('idempotency');
            Route::post('accounts/{financialAccount}/restore', [FinancialAccountController::class, 'restore'])->middleware('idempotency');

            Route::get('categories', [CategoryController::class, 'index']);
            Route::post('categories', [CategoryController::class, 'store'])->middleware('idempotency');
            Route::get('categories/{category}', [CategoryController::class, 'show']);
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->middleware('idempotency');
            Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->middleware('idempotency');
            Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->middleware('idempotency');
        });
    });
