<?php

namespace App\Http;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class CurrentCustomer
{
    public const string GUARD = 'customer';

    private ?Customer $customer = null;

    public function set(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function get(): Customer
    {
        return $this->customer
            ?? throw new LogicException('Demo customer identity has not been resolved.');
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    /**
     * Scope a direct customer-owned model query before resolving a resource.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function owned(Builder $query): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('customer_id'),
            $this->id(),
        );
    }

    /**
     * Resolve a direct customer-owned resource without exposing another customer's record.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    public function findOwnedOrFail(Builder $query, int|string $id): Model
    {
        return $this->owned($query)->findOrFail($id);
    }
}
