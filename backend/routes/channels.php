<?php

use App\Http\CurrentCustomer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'customers.{customerId}',
    fn (Customer $customer, string $customerId): bool => (string) $customer->id === $customerId,
    ['guards' => [CurrentCustomer::GUARD]],
);

Broadcast::channel(
    'admins.{adminId}',
    fn (User $user, string $adminId): bool => $user->is_admin && (string) $user->id === $adminId,
    ['guards' => ['web']],
);
