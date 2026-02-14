<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\ReviewSummaryDTO;
use App\DTO\Reviews\ReviewViewModel;
use App\Repositories\ReviewRepository;

class ReviewQueryService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository
    )
    {
    }

    public function getPaginatedProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $paginatedReviews = $this->reviewRepository->findByProduct($productId, $page, $perPage);

        $reviews = $paginatedReviews['data']->map(function ($review) {
            return new ReviewViewModel(
                id: $review->id,
                rating: $review->rating,
                title: $review->title,
                comment: $review->comment,
                authorName: $review->author_name,
                isVerifiedPurchase: $review->is_verified_purchase,
                helpfulCount: $review->helpful_count,
                unhelpfulCount: $review->unhelpful_count,
                formattedDate: $review->formatted_date,
                createdAt: $review->created_at
            );
        });

        return [
            'reviews' => $reviews->map(fn($vm) => $vm->toArray())->toArray(),
            'pagination' => $paginatedReviews['pagination']
        ];
    }

    public function getReviewSummary(int $productId): ReviewSummaryDTO
    {
        $averageRating = $this->reviewRepository->getAverageRating($productId);
        $totalReviews = $this->reviewRepository->getTotalReviewCount($productId);
        $breakdown = $this->reviewRepository->getRatingBreakdown($productId);

        $percentages = [];
        foreach ($breakdown as $rating => $count) {
            $percentages[$rating] = $totalReviews > 0
                ? round(($count / $totalReviews) * 100, 1)
                : 0;
        }

        return new ReviewSummaryDTO(
            averageRating: $averageRating,
            totalReviews: $totalReviews,
            ratingBreakdown: $breakdown,
            ratingPercentages: $percentages
        );
    }

    public function canUserReview(int $productId, ?int $userId): array
    {
        if (!$userId) {
            return [
                'can_review' => false,
                'reason' => 'You must be logged in to submit a review'
            ];
        }

        if ($this->reviewRepository->hasUserReviewedProduct($productId, $userId)) {
            return [
                'can_review' => false,
                'reason' => 'You have already reviewed this product'
            ];
        }

        return [
            'can_review' => true,
            'reason' => null
        ];
    }
}