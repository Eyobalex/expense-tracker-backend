<?php

use App\Domain\Admin\StaffPermission;
use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');

    Route::middleware(['auth:staff', 'staff.mfa'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::livewire('/', 'pages::admin.overview')
            ->middleware('staff.permission:'.StaffPermission::DashboardRead->value)
            ->name('overview');
    });
});
