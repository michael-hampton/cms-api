<?php

namespace App\DTO\Reviews;

/**
 * DTO for creating a review.
 *
 * Supports both legacy product reviews (productId set, planId null)
 * and new subscription plan reviews (planId set, productId null).
 *
 * The reviewable_type and reviewable_id fields are derived and used
 * in the polymorphic columns. product_id is still written for legacy
 * product reviews to remain backwards compatible.
 */
final class CreateReviewDTO
{
    public function __construct(
        public readonly ?int $productId,  // Null for plan reviews
        public readonly ?int $planId,     // Null for product reviews
        public readonly int    $userId,
        public readonly int    $rating,
        public readonly string $title,
        public readonly string $comment,
        public readonly int    $siteId,
    )
    {
    }

    /**
     * The fully-qualified model class name for the polymorphic relation.
     */
    public function reviewableType(): string
    {
        if ($this->planId !== null) {
            return \App\Models\SubscriptionPlan::class;
        }

        return \App\Models\Product::class;
    }

    /**
     * The ID of the reviewable entity.
     */
    public function reviewableId(): int
    {
        return $this->planId ?? $this->productId;
    }

    /**
     * Whether this review targets a subscription plan.
     */
    public function isForPlan(): bool
    {
        return $this->planId !== null;
    }

    public static function fromArray(array $data, int $userId, int $siteId): self
    {
        return new self(
            productId: isset($data['product_id']) ? (int)$data['product_id'] : null,
            planId: isset($data['plan_id']) ? (int)$data['plan_id'] : null,
            userId: $userId,
            rating: (int)($data['rating'] ?? 0),
            title: (string)($data['title'] ?? ''),
            comment: (string)($data['comment'] ?? ''),
            siteId: $siteId,
        );
    }
}