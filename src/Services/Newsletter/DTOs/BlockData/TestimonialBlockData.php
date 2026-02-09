<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class TestimonialBlockData extends BaseBlockData
{
    public function __construct(
        public readonly array $testimonials
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            testimonials: $data['testimonials'] ?? []
        );
    }
}