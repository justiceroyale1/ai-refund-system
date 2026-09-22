<?php

namespace App\Http\Controllers;

use App\Http\Resources\DemoCustomerResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DemoCustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return DemoCustomerResource::collection($customers);
    }
}
