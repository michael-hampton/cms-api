<?php

namespace App\DTO\PublicContent;

class PageReviewData
{
    public function __construct(
        public float $rating,
        public int $maxRating,
        public float $subRating, // <-- 1. ADD THIS CONSTRUCTOR VARIABLE
        public ?string $product,
        public ?string $category,
        public string $verdict,
        public array $pros,
        public array $cons
    ) {}

    /**
     * If your DTO has a toArray method, make sure both snake and camel keys are mapped
     */
    public function toArray(): array
    {
        return [
            'rating' => $this->rating,
            'max_rating' => $this->maxRating,
            'maxRating' => $this->maxRating,
            'sub_rating' => $this->subRating, // <-- 2. ENSURE EXPORT MAP CONTAINS IT
            'subRating' => $this->subRating,
            'product' => $this->product,
            'category' => $this->category,
            'verdict' => $this->verdict,
            'pros' => $this->pros,
            'cons' => $this->cons,
        ];
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }
}