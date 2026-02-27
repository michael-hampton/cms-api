<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\ReviewSummaryDTO;
use App\DTO\Reviews\ReviewViewModel;
use App\Models\SubscriptionPlan;
use App\Repositories\ReviewRepository;

class ReviewQueryService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository
    )
    {
    }

    // ─── Legacy product methods (backwards compatible) ─────────────────────

    public function getPaginatedProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $paginatedReviews = $this->reviewRepository->findByProduct($productId, $page, $perPage);
        return $this->formatPaginatedResult($paginatedReviews);
    }

    public function getReviewSummary(int $productId): ReviewSummaryDTO
    {
        return $this->buildSummaryDTO(
            $this->reviewRepository->getAverageRating($productId),
            $this->reviewRepository->getTotalReviewCount($productId),
            $this->reviewRepository->getRatingBreakdown($productId)
        );
    }

    public function canUserReview(int $productId, ?int $userId): array
    {
        if (!$userId) {
            return ['can_review' => false, 'reason' => 'You must be logged in to submit a review'];
        }

        if ($this->reviewRepository->hasUserReviewedProduct($productId, $userId)) {
            return ['can_review' => false, 'reason' => 'You have already reviewed this product'];
        }

        return ['can_review' => true, 'reason' => null];
    }

    // ─── Polymorphic plan methods ──────────────────────────────────────────

    public function getPaginatedPlanReviews(int $planId, int $page = 1, int $perPage = 10): array
    {
        $paginatedReviews = $this->reviewRepository->findByReviewable(
            SubscriptionPlan::class,
            $planId,
            $page,
            $perPage
        );
        return $this->formatPaginatedResult($paginatedReviews);
    }

    public function getPlanReviewSummary(int $planId): ReviewSummaryDTO
    {
        return $this->buildSummaryDTO(
            $this->reviewRepository->getAverageRatingForReviewable(SubscriptionPlan::class, $planId),
            $this->reviewRepository->getTotalReviewCountForReviewable(SubscriptionPlan::class, $planId),
            $this->reviewRepository->getRatingBreakdownForReviewable(SubscriptionPlan::class, $planId)
        );
    }

    public function canUserReviewPlan(int $planId, ?int $userId): array
    {
        if (!$userId) {
            return ['can_review' => false, 'reason' => 'You must be logged in to submit a review'];
        }

        if ($this->reviewRepository->hasUserReviewedReviewable(SubscriptionPlan::class, $planId, $userId)) {
            return ['can_review' => false, 'reason' => 'You have already reviewed this plan'];
        }

        return ['can_review' => true, 'reason' => null];
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function formatPaginatedResult(array $paginatedReviews): array
    {
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
                createdAt: $review->created_at?->format('Y-m-d H:i:s')
            );
        });

        return [
            'reviews' => $reviews->map(fn($vm) => $vm->toArray())->toArray(),
            'pagination' => $paginatedReviews['pagination'],
        ];
    }

    private function buildSummaryDTO(float $avg, int $total, array $breakdown): ReviewSummaryDTO
    {
        $percentages = [];
        foreach ($breakdown as $rating => $count) {
            $percentages[$rating] = $total > 0
                ? round(($count / $total) * 100, 1)
                : 0;
        }

        return new ReviewSummaryDTO(
            averageRating: $avg,
            totalReviews: $total,
            ratingBreakdown: $breakdown,
            ratingPercentages: $percentages
        );
    }
}