<?php

namespace App\Repositories\Product;

use App\Models\ProductVariant;
use App\Repositories\Repository;

class ProductVariantRepository extends Repository
{
    public function findByProduct(int $productId)
    {
        return $this->model->where('product_id', $productId)->get();
    }

    public function lockForUpdate(int $id)
    {
        return $this->model->lockForUpdate()->find($id);
    }

    protected function getModelClass(): string
    {
        return ProductVariant::class;
    }
}