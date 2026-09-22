<?php

use App\Http\CurrentCustomer;
use App\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'customers.{customerId}',
    fn (Customer $customer, string $customerId): bool => (string) $customer->getKey() === $customerId,
    ['guards' => [CurrentCustomer::GUARD]],
);
