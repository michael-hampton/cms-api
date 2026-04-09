<?php

namespace App\Jobs\Orders;

use App\Jobs\BaseJob;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\Preorder\Actions\AllocatePreorderStockAction;

class AllocatePreorderStockJob extends BaseJob
{
    private ProductRepository $productRepository;
    private AllocatePreorderStockAction $allocateAction;

    public function __construct(
        private readonly int $productId,
    )
    {
    }

    public function handle(): void
    {
        $product = $this->productRepository->find($this->productId);

        if (!$product) {
            return;
        }

        $this->allocateAction->execute($product);
    }
}