<?php

namespace App\Jobs\Orders;

use App\Jobs\BaseJob;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\Preorder\Actions\AllocatePreorderStockAction;

class AllocatePreorderStockJob extends BaseJob
{
    public function __construct(
        private readonly ProductRepository           $productRepository,
        private readonly AllocatePreorderStockAction $allocateAction
    )
    {
    }

    public function handle(int $productId): void
    {
        $product = $this->productRepository->find($productId);

        if (!$product) {
            return;
        }

        $this->allocateAction->execute($product);
    }
}