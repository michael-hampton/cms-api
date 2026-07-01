<?php

namespace App\DTO\PublicContent;

final readonly class PageReviewData
{
    public function __construct(
        public float $rating,
        public int $maxRating,
        public ?string $product,
        public ?string $category,
        public string $verdict,
        public array $pros,
        public array $cons,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rating' => $this->rating,
            'maxRating' => $this->maxRating,
            'product' => $this->product,
            'category' => $this->category,
            'verdict' => $this->verdict,
            'pros' => $this->pros,
            'cons' => $this->cons,
        ];
    }
}