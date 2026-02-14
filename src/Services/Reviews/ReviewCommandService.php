<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\CreateReviewDTO;
use App\DTO\Reviews\ReviewResult;
use App\DTO\Reviews\UpdateReviewDTO;
use App\Framework\Database\Database;
use App\Models\Review;
use App\Repositories\Product\ProductRepository;
use App\Repositories\ReviewRepository;

class ReviewCommandService
{
    public function __construct(
        private readonly Database                 $database,
        private readonly ReviewRepository         $reviewRepository,
        private readonly ProductRepository        $productRepository,
        private readonly ReviewPolicy             $reviewPolicy,
        private readonly VerifiedPurchaseResolver $verifiedPurchaseResolver
    )
    {
    }

    public function createReview(CreateReviewDTO $dto, int $currentUserId): ReviewResult
    {
        if (!$this->reviewPolicy->canCreate($currentUserId, $dto->productId)) {
            return ReviewResult::failure('You must be logged in to submit a review');
        }

        $product = $this->productRepository->find($dto->productId);
        if (!$product) {
            return ReviewResult::failure('Product not found');
        }

        if ($this->reviewRepository->hasUserReviewedProduct($dto->productId, $dto->userId)) {
            return ReviewResult::failure('You have already reviewed this product');
        }

        if ($dto->rating < 1 || $dto->rating > 5) {
            return ReviewResult::failure('Rating must be between 1 and 5');
        }

        $isVerifiedPurchase = $this->verifiedPurchaseResolver->isVerified(
            $dto->userId,
            $dto->productId
        );

        $review = $this->database->transaction(function () use ($dto, $product, $isVerifiedPurchase) {
            return $this->reviewRepository->create([
                'product_id' => $dto->productId,
                'user_id' => $dto->userId,
                'rating' => $dto->rating,
                'title' => $dto->title,
                'comment' => $dto->comment,
                'is_verified_purchase' => $isVerifiedPurchase,
                'is_approved' => true,
                'helpful_count' => 0,
                'unhelpful_count' => 0,
                'site_id' => $product->site_id
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
}