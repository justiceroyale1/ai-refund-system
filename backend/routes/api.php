<?php

use App\Http\Controllers\DemoCustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/demo/customers', [DemoCustomerController::class, 'index'])
    ->name('demo.customers.index');
