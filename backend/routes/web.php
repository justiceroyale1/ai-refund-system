<?php

use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\RefundRequestController;
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
            Route::get('/dashboard', [AdminDashboardController::class, 'show'])
                ->name('dashboard.show');
            Route::get('/notifications', [AdminNotificationController::class, 'index'])
                ->name('notifications.index');
            Route::patch('/notifications/{notification}/read', [AdminNotificationController::class, 'read'])
                ->whereUuid('notification')
                ->name('notifications.read');
            Route::patch('/notifications/read-all', [AdminNotificationController::class, 'readAll'])
                ->name('notifications.read-all');
            Route::get('/refund-requests', [RefundRequestController::class, 'index'])
                ->name('refund-requests.index');
            Route::get('/refund-requests/{refundRequest}', [RefundRequestController::class, 'show'])
                ->name('refund-requests.show');
            Route::post('/refund-requests/{refundRequest}/review', [RefundRequestController::class, 'review'])
                ->name('refund-requests.review');
            Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
            Route::get('/me', [AdminAuthenticatedSessionController::class, 'show'])
                ->name('me');
        });
    });
