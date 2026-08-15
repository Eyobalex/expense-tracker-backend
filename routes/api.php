<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\FinancialAccountController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\MerchantController;
use App\Http\Controllers\Api\V1\NormalizationCandidateController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionDuplicateController;
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
            Route::get('exchange-rates', [ExchangeRateController::class, 'index']);
            Route::put('onboarding', [OnboardingController::class, 'update'])->middleware('idempotency');

            Route::get('accounts', [FinancialAccountController::class, 'index']);
            Route::post('accounts', [FinancialAccountController::class, 'store'])->middleware('idempotency');
            Route::get('accounts/{financialAccount}', [FinancialAccountController::class, 'show']);
            Route::patch('accounts/{financialAccount}', [FinancialAccountController::class, 'update'])->middleware('idempotency');
            Route::post('accounts/{financialAccount}/archive', [FinancialAccountController::class, 'archive'])->middleware('idempotency');
            Route::post('accounts/{financialAccount}/restore', [FinancialAccountController::class, 'restore'])->middleware('idempotency');

            Route::get('transactions', [TransactionController::class, 'index']);
            Route::post('transactions', [TransactionController::class, 'store'])->middleware('idempotency');
            Route::get('transactions/balances', [TransactionController::class, 'balances']);
            Route::get('transactions/{transaction}/duplicates', [TransactionDuplicateController::class, 'index']);
            Route::post('transactions/{transaction}/duplicate-decision', [TransactionDuplicateController::class, 'resolve'])->middleware('idempotency');
            Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
            Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->middleware('idempotency');
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->middleware('idempotency');
            Route::post('transactions/{transaction}/post', [TransactionController::class, 'post'])->middleware('idempotency');
            Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->middleware('idempotency');
            Route::post('transactions/{transaction}/correct', [TransactionController::class, 'correct'])->middleware('idempotency');

            Route::get('budgets', [BudgetController::class, 'index']);
            Route::get('budgets/{category}/periods', [BudgetController::class, 'history']);
            Route::post('budgets/{category}/reallocate', [BudgetController::class, 'reallocate'])->middleware('idempotency');
            Route::post('budgets/{category}/borrow-next-month', [BudgetController::class, 'borrow'])->middleware('idempotency');

            Route::get('merchants', [MerchantController::class, 'index']);
            Route::post('merchants', [MerchantController::class, 'store'])->middleware('idempotency');
            Route::post('merchants/{merchant}/merge', [MerchantController::class, 'merge'])->middleware('idempotency');
            Route::get('items', [ItemController::class, 'index']);
            Route::post('items', [ItemController::class, 'store'])->middleware('idempotency');
            Route::get('normalization-candidates', [NormalizationCandidateController::class, 'index']);
            Route::post('normalization-candidates/{candidate}/resolve', [NormalizationCandidateController::class, 'resolve'])->middleware('idempotency');
            Route::post('items/{item}/merge', [ItemController::class, 'merge'])->middleware('idempotency');

            Route::get('receipts', [ReceiptController::class, 'index']);
            Route::post('receipts', [ReceiptController::class, 'store'])->middleware(['idempotency', 'throttle:receipt-upload']);
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);
            Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download']);
            Route::post('receipts/{receipt}/retry', [ReceiptController::class, 'retry'])->middleware(['idempotency', 'throttle:receipt-upload']);
            Route::post('receipts/{receipt}/review-transaction', [ReceiptController::class, 'createReviewTransaction'])->middleware('idempotency');

            Route::get('categories', [CategoryController::class, 'index']);
            Route::post('categories', [CategoryController::class, 'store'])->middleware('idempotency');
            Route::get('categories/{category}', [CategoryController::class, 'show']);
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->middleware('idempotency');
            Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->middleware('idempotency');
            Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->middleware('idempotency');
        });
    });
