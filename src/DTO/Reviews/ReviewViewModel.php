<?php

namespace App\DTO\Reviews;

class ReviewViewModel
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $rating,
        public readonly string $title,
        public readonly string $comment,
        public readonly string $authorName,
        public readonly bool   $isVerifiedPurchase,
        public readonly int    $helpfulCount,
        public readonly int    $unhelpfulCount,
        public readonly string $formattedDate,
        public readonly string $createdAt
    )
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title ?? '',
            'comment' => $this->comment,
            'author_name' => $this->authorName,
            'is_verified_purchase' => $this->isVerifiedPurchase,
            'helpful_count' => $this->helpfulCount,
            'unhelpful_count' => $this->unhelpfulCount,
            'formatted_date' => $this->formattedDate,
            'created_at' => $this->createdAt
        ];
    }
}