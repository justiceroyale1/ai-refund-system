<?php

use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/admin')
    ->name('admin.')
    ->group(function (): void {
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login');

        Route::middleware(['auth:sanctum', 'can:access-admin'])->group(function (): void {
            Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
            Route::get('/me', [AdminAuthenticatedSessionController::class, 'show'])
                ->name('me');
        });
    });
