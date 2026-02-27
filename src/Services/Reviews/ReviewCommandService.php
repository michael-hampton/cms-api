<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\CreateReviewDTO;
use App\DTO\Reviews\ReviewResult;
use App\DTO\Reviews\UpdateReviewDTO;
use App\Framework\Database\Database;
use App\Models\SubscriptionPlan;
use App\Repositories\Product\ProductRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class ReviewCommandService
{
    public function __construct(
        private readonly Database                   $database,
        private readonly ReviewRepository           $reviewRepository,
        private readonly ProductRepository          $productRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly ReviewPolicy               $reviewPolicy,
        private readonly VerifiedPurchaseResolver   $verifiedPurchaseResolver
    )
    {
    }

    public function createReview(CreateReviewDTO $dto, int $currentUserId): ReviewResult
    {
        if (!$this->reviewPolicy->canCreate($currentUserId, $dto->reviewableId())) {
            return ReviewResult::failure('You must be logged in to submit a review');
        }

        // Resolve the reviewable entity (product or plan)
        [$entity, $error] = $this->resolveReviewableEntity($dto);
        if ($error !== null) {
            return ReviewResult::failure($error);
        }

        // Duplicate review guard — polymorphic
        if ($this->reviewRepository->hasUserReviewedReviewable(
            $dto->reviewableType(),
            $dto->reviewableId(),
            $dto->userId
        )) {
            return ReviewResult::failure('You have already reviewed this');
        }

        // Legacy product_id guard (ensures existing data integrity)
        if ($dto->productId !== null
            && $this->reviewRepository->hasUserReviewedProduct($dto->productId, $dto->userId)
        ) {
            return ReviewResult::failure('You have already reviewed this product');
        }

        if ($dto->rating < 1 || $dto->rating > 5) {
            return ReviewResult::failure('Rating must be between 1 and 5');
        }

        $isVerifiedPurchase = $this->resolveVerifiedPurchase($dto);

        $review = $this->database->transaction(function () use ($dto, $entity, $isVerifiedPurchase) {
            return $this->reviewRepository->create([
                // Polymorphic columns (canonical)
                'reviewable_type' => $dto->reviewableType(),
                'reviewable_id' => $dto->reviewableId(),
                // Legacy column — populated for product reviews only
                'product_id' => $dto->productId,
                'user_id' => $dto->userId,
                'rating' => $dto->rating,
                'title' => $dto->title,
                'comment' => $dto->comment,
                'is_verified_purchase' => $isVerifiedPurchase,
                'is_approved' => true,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'site_id' => $entity->site_id,
            ]);
        });

        return ReviewResult::success('Review submitted successfully', $review);
    }

    public function updateReview(int $reviewId, UpdateReviewDTO $dto, int $currentUserId): ReviewResult
    {
        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            return ReviewResult::failure('Review not found');
        }

        if (!$this->reviewPolicy->canEdit($review, $currentUserId)) {
            return ReviewResult::failure('You can only edit your own reviews');
        }

        $updateData = $dto->toArray();

        if (isset($updateData['rating']) && ($updateData['rating'] < 1 || $updateData['rating'] > 5)) {
            return ReviewResult::failure('Rating must be between 1 and 5');
        }

        $this->database->transaction(function () use ($reviewId, $updateData) {
            $this->reviewRepository->update($reviewId, $updateData);
        });

        return ReviewResult::success('Review updated successfully');
    }

    public function deleteReview(int $reviewId, int $currentUserId): ReviewResult
    {
        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            return ReviewResult::failure('Review not found');
        }

        if (!$this->reviewPolicy->canDelete($review, $currentUserId)) {
            return ReviewResult::failure('You can only delete your own reviews');
        }

        $this->database->transaction(function () use ($reviewId) {
            $this->reviewRepository->delete($reviewId);
        });

        return ReviewResult::success('Review deleted successfully');
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    /**
     * Returns [entity, null] on success, [null, errorMessage] on failure.
     */
    private function resolveReviewableEntity(CreateReviewDTO $dto): array
    {
        if ($dto->isForPlan()) {
            $plan = $this->planRepository->find($dto->planId);
            if (!$plan) {
                return [null, 'Subscription plan not found'];
            }
            return [$plan, null];
        }

        $product = $this->productRepository->find($dto->productId);
        if (!$product) {
            return [null, 'Product not found'];
        }
        return [$product, null];
    }

    private function resolveVerifiedPurchase(CreateReviewDTO $dto): bool
    {
        // Verified purchase only applies to products for now
        if ($dto->isForPlan()) {
            return false;
        }

        return $this->verifiedPurchaseResolver->isVerified(
            $dto->userId,
            $dto->productId
        );
    }
}