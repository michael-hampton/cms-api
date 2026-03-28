<?php

declare(strict_types=1);

namespace App\Events\Products;

use App\Models\ProductFulfilment;

/**
 * Fired after a ProductFulfilment record is persisted.
 *
 * Listeners:
 *   - ProductFulfilmentCreatedListener (observability + future supplier notification)
 */
final class ProductFulfilmentCreated
{
    public function __construct(
        public readonly ProductFulfilment $fulfilment,
    )
    {
    }
}