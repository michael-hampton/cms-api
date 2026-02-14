<?php

namespace App\Services\Reviews;

class VerifiedPurchaseResolver
{
    public function isVerified(int $userId, int $productId): bool
    {
        // TODO: Implement when order system is available
        // Check if user has purchased this product
        return false;
    }
}