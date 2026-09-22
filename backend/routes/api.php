<?php

use App\Http\Controllers\Customer\RefundConversationController;
use App\Http\Controllers\DemoCustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/demo/customers', [DemoCustomerController::class, 'index'])
    ->name('demo.customers.index');

Route::prefix('customer')
    ->name('customer.')
    ->middleware('demo.customer')
    ->group(function (): void {
        Route::get('/conversations', [RefundConversationController::class, 'index'])
            ->name('conversations.index');
        Route::post('/conversations', [RefundConversationController::class, 'store'])
            ->name('conversations.store');
        Route::get('/conversations/{conversation}', [RefundConversationController::class, 'show'])
            ->where('conversation', '[1-9][0-9]*')
            ->name('conversations.show');
    });
