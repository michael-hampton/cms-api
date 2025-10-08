<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\Review;

class ReviewRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Review::class;
    }

    public function findByProduct(int $productId, int $page = 1, int $perPage = 10): array
    {
        $query = $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc');

        return $query->paginate($perPage, $page);
    }

    public function getAverageRating(int $productId): float
    {
        $avg = $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->avg('rating');

        return $avg ? round((float)$avg, 1) : 0.0;
    }

    public function getTotalReviewCount(int $productId): int
    {
        return $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->count();
    }

    public function getRatingBreakdown(int $productId): array
    {
        $reviews = $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->select(['rating'])
            ->get();

        $breakdown = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];

        foreach ($reviews as $review) {
            $breakdown[$review->rating]++;
        }

        return $breakdown;
    }

    public function hasUserReviewedProduct(int $productId, int $userId): bool
    {
        return $this->model->query()
            ->where('product_id', $productId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function findByUser(int $userId): Collection
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function incrementHelpfulCount(int $reviewId): bool
    {
        $review = $this->find($reviewId);
        if (!$review) {
            return false;
        }

        $review->update([
            'helpful_count' => $review->helpful_count + 1
        ]);

        return true;
    }

    public function incrementUnhelpfulCount(int $reviewId): bool
    {
        $review = $this->find($reviewId);
        if (!$review) {
            return false;
        }

        $review->update([
            'unhelpful_count' => $review->unhelpful_count + 1
        ]);

        return true;
    }

    public function getVerifiedPurchaseReviews(int $productId): Collection
    {
        return $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->where('is_verified_purchase', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}