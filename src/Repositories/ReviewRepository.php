<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Review;

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
    /**
     * Average star rating for all approved reviews on this merchant's products.
     *
     * Supports an optional monthsAgo offset to compare against a prior period.
     */
    public function averageRatingForMerchant(int $merchantId, int $monthsAgo = 0): float
    {
        $query = $this->baseQuery($merchantId)
            ->where('reviews.is_approved', 1);

        if ($monthsAgo > 0) {
            $start = now_datetime()->subMonths($monthsAgo)->startOfMonth();
            $end = now_datetime()->subMonths($monthsAgo)->endOfMonth();
            $query->whereBetween('reviews.created_at', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        } else {
            $query->whereMonth('reviews.created_at', now('m'))
                ->whereYear('reviews.created_at', now('Y'));
        }

        return round((float)$query->avg('reviews.rating'), 1);
    }

    /**
     * Most recent approved reviews for a merchant's products.
     */
    public function recentForMerchant(int $merchantId, int $limit = 10): Collection
    {
        return $this->baseQuery($merchantId)
            ->where('reviews.is_approved', 1)
            ->orderByDesc('reviews.created_at')
            ->limit($limit)
            ->select([
                'reviews.*',
                'products.name as product_name',
            ])
            ->get();
    }

    /**
     * Aggregate stats for the merchant's reviews.
     *
     * @return array{
     *     total: int,
     *     pending_response: int,
     *     this_month: int,
     *     previous_month: int,
     *     rating_distribution: array<int, float>,
     * }
     */
    public function statsForMerchant(int $merchantId): array
    {
        $base = $this->baseQuery($merchantId)->where('reviews.is_approved', 1);

        $total = (clone $base)->count();

        $pendingResponse = (clone $base)->count();

        $thisMonth = (clone $base)
            ->whereMonth('reviews.created_at', now('MM'))
            ->whereYear('reviews.created_at', now('YYYY'))
            ->count();

        $previousMonth = (clone $base)
            ->whereMonth('reviews.created_at', now_datetime()->subMonths(1)->format('MM'))
            ->whereYear('reviews.created_at', now_datetime()->subMonths(1)->format('YYYY'))
            ->count();

        // Distribution: percentage per star (1–5)
        $distribution = (clone $base)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();

        $ratingDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = $distribution[$star] ?? 0;
            $ratingDistribution[$star] = $total > 0
                ? round(($count / $total) * 100, 1)
                : 0.0;
        }

        return [
            'total' => $total,
            'pending_response' => $pendingResponse,
            'this_month' => $thisMonth,
            'previous_month' => $previousMonth,
            'rating_distribution' => $ratingDistribution,
        ];
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function baseQuery(int $merchantId)
    {
        return $this->model->newQuery()
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->join('product_merchants', 'product_merchants.product_id', '=', 'products.id')
            ->where('product_merchants.merchant_id', $merchantId);
    }

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