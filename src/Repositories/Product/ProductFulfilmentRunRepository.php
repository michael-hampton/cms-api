<?php

declare(strict_types=1);

namespace App\Repositories\Product;

use App\Models\ProductFulfilmentRun;
use App\Repositories\Repository;

/**
 * Persistence for ProductFulfilmentRun records. No business logic.
 */
class ProductFulfilmentRunRepository extends Repository
{
    protected function getModelClass(): string
    {
        return ProductFulfilmentRun::class;
    }
}