<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubscriptionPlan;

/**
 * ReviewRepository
 *
 * All existing product-based methods remain fully backwards compatible.
 * New polymorphic methods accept a reviewable_type + reviewable_id pair
 * and are used for SubscriptionPlan reviews (and any future entity).
 *
 * Internal convention:
 *   - Legacy product methods call `_buildProductQuery()` which filters by
 *     product_id (the old column) for maximum backwards compatibility.
 *   - New polymorphic methods call `_buildReviewableQuery()` which filters by
 *     reviewable_type + reviewable_id.
 */
class ReviewRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Review::class;
    }

    // ─── Legacy product-scoped methods (backwards compatible) ─────────────

    public function findByProduct(int $productId, int $page = 1, int $perPage = 10): array
    {
        $query = $this->_buildProductQuery($productId)
            ->with(['user'])
            ->orderBy('id', 'desc');

        return $query->paginate($perPage, $page);
    }

    public function getAverageRating(int $productId): float
    {
        $avg = $this->_buildProductQuery($productId)->avg('rating');
        return $avg ? round((float)$avg, 1) : 0.0;
    }

    public function getTotalReviewCount(int $productId): int
    {
        return $this->_buildProductQuery($productId)->count();
    }

    public function getRatingBreakdown(int $productId): array
    {
        return $this->buildRatingBreakdown(
            $this->_buildProductQuery($productId)->select(['rating'])->get()
        );
    }

    public function hasUserReviewedProduct(int $productId, int $userId): bool
    {
        return $this->model->query()
            ->where('product_id', $productId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getVerifiedPurchaseReviews(int $productId): Collection
    {
        return $this->_buildProductQuery($productId)
            ->where('is_verified_purchase', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getTopReview(array $productIds): ?Collection
    {
        return $this->model->query()
            ->whereIn('product_id', $productIds)
            ->where('is_approved', true)
            ->orderBy('helpful_count', 'desc')
            ->orderBy('rating', 'desc')
            ->get();
    }

    // ─── Polymorphic methods (new — used for SubscriptionPlan etc.) ────────

    /**
     * Paginated approved reviews for any reviewable entity.
     */
    public function findByReviewable(
        string $type,
        int    $id,
        int    $page = 1,
        int    $perPage = 10
    ): array
    {
        $query = $this->_buildReviewableQuery($type, $id)
            ->with(['user'])
            ->orderBy('created_at', 'desc');

        return $query->paginate($perPage, $page);
    }

    public function getAverageRatingForReviewable(string $type, int $id): float
    {
        $avg = $this->_buildReviewableQuery($type, $id)->avg('rating');
        return $avg ? round((float)$avg, 1) : 0.0;
    }

    public function getTotalReviewCountForReviewable(string $type, int $id): int
    {
        return $this->_buildReviewableQuery($type, $id)->count();
    }

    public function getRatingBreakdownForReviewable(string $type, int $id): array
    {
        return $this->buildRatingBreakdown(
            $this->_buildReviewableQuery($type, $id)->select(['rating'])->get()
        );
    }

    public function hasUserReviewedReviewable(string $type, int $id, int $userId): bool
    {
        return $this->model->query()
            ->where('reviewable_type', $type)
            ->where('reviewable_id', $id)
            ->where('user_id', $userId)
            ->exists();
    }

    // ─── User-scoped (entity-agnostic) ────────────────────────────────────

    public function findByUser(int $userId): Collection
    {
        return $this->model->query()
            ->where('user_id', $userId)
            ->with(['product'])
            ->orderBy('id', 'desc')
            ->get();
    }

    // ─── Vote helpers ─────────────────────────────────────────────────────

    public function incrementHelpfulCount(int $reviewId): bool
    {
        $review = $this->find($reviewId);
        if (!$review) {
            return false;
        }

        $review->update([
            'helpful_count' => $review->helpful_count + 1,
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
            'unhelpful_count' => $review->unhelpful_count + 1,
        ]);

        return true;
    }

    // ─── Private query builders ───────────────────────────────────────────

    /**
     * Base query scoped to approved reviews for a legacy product_id.
     * Kept separate so it is easy to remove when product_id is eventually dropped.
     */
    private function _buildProductQuery(int $productId)
    {
        return $this->model->query()
            ->where('product_id', $productId)
            ->where('is_approved', true);
    }

    /**
     * Base query scoped to approved reviews for a polymorphic reviewable.
     */
    private function _buildReviewableQuery(string $type, int $id)
    {
        return $this->model->query()
            ->where('reviewable_type', $type)
            ->where('reviewable_id', $id)
            ->where('is_approved', true);
    }

    /**
     * Aggregate a collection of rating rows into a [5 => n, 4 => n, ...] breakdown.
     */
    private function buildRatingBreakdown(Collection $rows): array
    {
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($rows as $review) {
            if (isset($breakdown[$review->rating])) {
                $breakdown[$review->rating]++;
            }
        }

        return $breakdown;
    }
}