<?php

namespace App\Services\Reviews;

use App\Models\Review;

class ReviewPolicy
{
    public function canCreate(?int $userId, int $productId): bool
    {
        return $userId !== null;
    }

    public function canEdit(Review $review, int $userId): bool
    {
        return $review->user_id === $userId;
    }

    public function canDelete(Review $review, int $userId): bool
    {
        return $review->user_id === $userId;
    }

    public function canVote(?int $userId, string $sessionId): bool
    {
        return true; // Anyone can vote
    }
}