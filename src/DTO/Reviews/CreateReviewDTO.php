<?php

namespace App\DTO\Reviews;

class CreateReviewDTO
{
    public function __construct(
        public readonly int    $productId,
        public readonly int    $userId,
        public readonly int    $rating,
        public readonly string $title,
        public readonly string $comment,
        public readonly int    $siteId,
        public readonly bool   $isVerifiedPurchase = false
    )
    {
    }

    public static function fromArray(array $data, int $userId, int $siteId): self
    {
        return new self(
            productId: $data['product_id'],
            userId: $userId,
            rating: (int)$data['rating'],
            title: $data['title'] ?? '',
            comment: $data['comment'] ?? '',
            siteId: $siteId,
            isVerifiedPurchase: $data['is_verified_purchase'] ?? false
        );
    }
}