<?php

namespace App\DTO\Boost;

use App\Enums\Boost\SuggestionType;

final class BoostSuggestionDTO
{
    public function __construct(
        public readonly int            $productId,
        public readonly string         $productName,
        public readonly string         $boostableType,      // product | offer
        public readonly ?int           $offerId,
        public readonly SuggestionType $type,
        public readonly string         $reason,             // Human-readable
        public readonly float          $opportunityScore,
        public readonly string         $suggestedContext,
        public readonly float          $suggestedMultiplier,
        public readonly float          $estimatedCost,      // price_paid preview
        public readonly int            $impressionsLast30d,
        public readonly float          $conversionRate,
        public readonly int            $stockQuantity,
        public readonly float          $discountPercent,
        public readonly float          $averageRating,
        public readonly ?int           $daysUntilBoostExpiry, // null if no active boost
    )
    {
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'boostable_type' => $this->boostableType,
            'offer_id' => $this->offerId,
            'type' => $this->type->value,
            'reason' => $this->reason,
            'opportunity_score' => $this->opportunityScore,
            'suggested_context' => $this->suggestedContext,
            'suggested_multiplier' => $this->suggestedMultiplier,
            'estimated_cost' => $this->estimatedCost,
            'impressions_last_30d' => $this->impressionsLast30d,
            'conversion_rate' => $this->conversionRate,
            'stock_quantity' => $this->stockQuantity,
            'discount_percent' => $this->discountPercent,
            'average_rating' => $this->averageRating,
            'days_until_boost_expiry' => $this->daysUntilBoostExpiry,
        ];
    }
}