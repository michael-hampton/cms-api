<?php

namespace App\Repositories;

use App\Models\Model;
use App\Models\Product;
use App\Models\ProductView;

class ProductViewRepository extends Repository
{
    protected function getModelClass(): string
    {
        return ProductView::class;
    }

    public function trackView(Product $product, ?int $userId, string $sessionId, ?string $ipAddress): Model
    {
        return $this->create([
            'product_id' => $product->id,
            'site_id' => $product->site_id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
        ]);
    }
}