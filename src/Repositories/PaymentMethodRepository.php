<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\PaymentMethod;

class PaymentMethodRepository extends Repository
{
    public function findByCode(string $code): ?PaymentMethod
    {
        return $this->applySiteFilter(
            PaymentMethod::where('code', $code)
        )->first();
    }

    public function getActive(): Collection
    {
        return $this->applySiteFilter(
            PaymentMethod::where('is_active', true)
                ->orderBy('sort_order', 'asc')
        )->get();
    }

    public function getAllOrdered(): Collection
    {
        return $this->applySiteFilter(
            PaymentMethod::orderBy('sort_order', 'asc')
        )->get();
    }

    public function getByProvider(string $provider): Collection
    {
        return $this->applySiteFilter(
            PaymentMethod::where('provider', $provider)
        )->get();
    }

    protected function getModelClass(): string
    {
        return PaymentMethod::class;
    }
}