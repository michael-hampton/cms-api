<?php

namespace App\DTO\Reviews;

class ReviewSummaryDTO
{
    public function __construct(
        public readonly float $averageRating,
        public readonly int   $totalReviews,
        public readonly array $ratingBreakdown,
        public readonly array $ratingPercentages
    )
    {
    }

    public function toArray(): array
    {
        return [
            'average_rating' => $this->averageRating,
            'total_reviews' => $this->totalReviews,
            'rating_breakdown' => $this->ratingBreakdown,
            'rating_percentages' => $this->ratingPercentages
        ];
    }
}