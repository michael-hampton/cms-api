<?php

namespace App\Services;

use App\Framework\Authorization\AuthenticationService;
use App\Models\Review;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewHelpfulRepository;
use App\Repositories\ReviewRepository;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewHelpfulRepository $reviewHelpfulRepository,
        private readonly ProductRepository $productRepository,
        private readonly AuthenticationService $authService
    ) {}

    protected function getSessionId(): string
    {
        if (!isset($_SESSION['review_session_id'])) {
            $_SESSION['review_session_id'] = uniqid('review_', true);
        }
        return $_SESSION['review_session_id'];
    }

    public function getProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $paginatedReviews = $this->reviewRepository->findByProduct($productId, $page, $perPage);

        $reviews = $paginatedReviews['data']->map(function($review) {

            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'author_name' => $review->author_name,
                'is_verified_purchase' => $review->is_verified_purchase,
                'helpful_count' => $review->helpful_count,
                'unhelpful_count' => $review->unhelpful_count,
                'formatted_date' => $review->formatted_date,
                'created_at' => $review->created_at,
            ];
        });

        return [
            'reviews' => $reviews->toArray(),
            'pagination' => $paginatedReviews['pagination'],
            'average_rating' => $this->reviewRepository->getAverageRating($productId),
            'total_reviews' => $this->reviewRepository->getTotalReviewCount($productId),
            'rating_breakdown' => $this->reviewRepository->getRatingBreakdown($productId),
        ];
    }

    public function createReview(int $productId, array $data): array
    {
        $userId = $this->authService->getUserId();

        if (!$userId) {
            return ['success' => false, 'message' => 'You must be logged in to submit a review'];
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // Check if user already reviewed
        if ($this->reviewRepository->hasUserReviewedProduct($productId, $userId)) {
            return ['success' => false, 'message' => 'You have already reviewed this product'];
        }

        // Validate rating
        if (!isset($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }

        // Check if verified purchase (you'll need to implement this logic)
        $isVerifiedPurchase = $this->isVerifiedPurchase($userId, $productId);

        $review = $this->reviewRepository->create([
            'product_id' => $productId,
            'user_id' => $userId,
            'rating' => (int)$data['rating'],
            'title' => $data['title'] ?? '',
            'comment' => $data['comment'] ?? '',
            'is_verified_purchase' => $isVerifiedPurchase,
            'is_approved' => true, // Auto-approve or set to false for moderation
            'helpful_count' => 0,
            'unhelpful_count' => 0,
            'site_id' => $product->site_id,
        ]);

        return [
            'success' => true,
            'message' => 'Review submitted successfully',
            'review' => $review->toArray()
        ];
    }

    public function updateReview(int $reviewId, array $data): array
    {
        $userId = $this->authService->getUserId();

        if (!$userId) {
            return ['success' => false, 'message' => 'You must be logged in'];
        }

        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }

        if ($review->user_id !== $userId) {
            return ['success' => false, 'message' => 'You can only edit your own reviews'];
        }

        // Validate rating if provided
        if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }

        $updateData = [];
        if (isset($data['rating'])) $updateData['rating'] = (int)$data['rating'];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['comment'])) $updateData['comment'] = $data['comment'];

        $this->reviewRepository->update($reviewId, $updateData);

        return [
            'success' => true,
            'message' => 'Review updated successfully'
        ];
    }

    public function deleteReview(int $reviewId): array
    {
        $userId = $this->authService->getUserId();

        if (!$userId) {
            return ['success' => false, 'message' => 'You must be logged in'];
        }

        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }

        if ($review->user_id !== $userId) {
            return ['success' => false, 'message' => 'You can only delete your own reviews'];
        }

        $this->reviewRepository->delete($reviewId);

        return [
            'success' => true,
            'message' => 'Review deleted successfully'
        ];
    }

    public function markReviewHelpful(int $reviewId, bool $isHelpful): array
    {
        $sessionId = $this->getSessionId();
        $userId = $this->authService->getUserId();

        $review = $this->reviewRepository->find($reviewId);
        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }

        // Check if user already voted
        $existingVote = $this->reviewHelpfulRepository->getUserVote($reviewId, $userId, $sessionId);

        if ($existingVote) {
            // If voting the same way, remove vote
            if ($existingVote->is_helpful === $isHelpful) {
                // Decrement count
                if ($isHelpful) {
                    $review->update(['helpful_count' => max(0, $review->helpful_count - 1)]);
                } else {
                    $review->update(['unhelpful_count' => max(0, $review->unhelpful_count - 1)]);
                }

                $this->reviewHelpfulRepository->delete($existingVote->id);

                return [
                    'success' => true,
                    'message' => 'Vote removed',
                    'helpful_count' => $review->helpful_count,
                    'unhelpful_count' => $review->unhelpful_count
                ];
            } else {
                // Change vote
                if ($isHelpful) {
                    $review->update([
                        'helpful_count' => $review->helpful_count + 1,
                        'unhelpful_count' => max(0, $review->unhelpful_count - 1)
                    ]);
                } else {
                    $review->update([
                        'helpful_count' => max(0, $review->helpful_count - 1),
                        'unhelpful_count' => $review->unhelpful_count + 1
                    ]);
                }

                $this->reviewHelpfulRepository->update($existingVote->id, [
                    'is_helpful' => $isHelpful
                ]);

                return [
                    'success' => true,
                    'message' => 'Vote updated',
                    'helpful_count' => $review->helpful_count,
                    'unhelpful_count' => $review->unhelpful_count
                ];
            }
        }

        // Create new vote
        $this->reviewHelpfulRepository->create([
            'review_id' => $reviewId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'is_helpful' => $isHelpful,
            'site_id' => $review->site_id,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        // Increment count
        if ($isHelpful) {
            $this->reviewRepository->incrementHelpfulCount($reviewId);
        } else {
            $this->reviewRepository->incrementUnhelpfulCount($reviewId);
        }

        $review = $this->reviewRepository->find($reviewId);

        return [
            'success' => true,
            'message' => 'Thank you for your feedback',
            'helpful_count' => $review->helpful_count,
            'unhelpful_count' => $review->unhelpful_count
        ];
    }

    public function getReviewStatistics(int $productId): array
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

        return [
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'rating_breakdown' => $breakdown,
            'rating_percentages' => $percentages,
        ];
    }

    protected function isVerifiedPurchase(int $userId, int $productId): bool
    {
        // Check if user has purchased this product
        // This requires an Order/OrderItem system
        // For now, return false
        // TODO: Implement when order system is available
        return false;
    }

    public function canUserReview(int $productId): array
    {
        $userId = $this->authService->getUserId();

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