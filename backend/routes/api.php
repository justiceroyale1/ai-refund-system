<?php

use App\Http\Controllers\Customer\ConversationMessageController;
use App\Http\Controllers\Customer\CustomerNotificationController;
use App\Http\Controllers\Customer\RefundConversationController;
use App\Http\Controllers\DemoCustomerController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/demo/customers', [DemoCustomerController::class, 'index'])
    ->name('demo.customers.index');

Route::prefix('customer')
    ->name('customer.')
    ->middleware('demo.customer')
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)
    ->group(function (): void {
        Route::get('/conversations', [RefundConversationController::class, 'index'])
            ->name('conversations.index');
        Route::post('/conversations', [RefundConversationController::class, 'store'])
            ->name('conversations.store');
        Route::get('/conversations/{conversation}', [RefundConversationController::class, 'show'])
            ->where('conversation', '[1-9][0-9]*')
            ->name('conversations.show');
        Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
            ->where('conversation', '[1-9][0-9]*')
            ->name('conversations.messages.store');
        Route::get('/notifications', [CustomerNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [CustomerNotificationController::class, 'read'])
            ->whereUuid('notification')
            ->name('notifications.read');
        Route::patch('/notifications/read-all', [CustomerNotificationController::class, 'readAll'])
            ->name('notifications.read-all');
    });
