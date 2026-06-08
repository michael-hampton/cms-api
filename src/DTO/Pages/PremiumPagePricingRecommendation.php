<?php

namespace App\Services\Cms\Pages;

final class PremiumPagePricingRecommendation
{
    /**
     * @param array<int, string> $reasons
     */
    public function __construct(
        public readonly int $recommendedPrice,
        public readonly int $minimumPrice,
        public readonly int $maximumPrice,
        public readonly int $score,
        public readonly array $reasons,
        public readonly int $wordCount,
    ) {
    }

    public function toArray(): array
    {
        return [
            'recommended_price' => $this->recommendedPrice,
            'recommended_price_formatted' => '£' . number_format($this->recommendedPrice / 100, 2),
            'minimum_price' => $this->minimumPrice,
            'maximum_price' => $this->maximumPrice,
            'score' => $this->score,
            'reasons' => $this->reasons,
            'word_count' => $this->wordCount,
        ];
    }
}