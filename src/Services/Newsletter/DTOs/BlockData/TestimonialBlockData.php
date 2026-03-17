<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TestimonialBlockData extends BaseBlockData
{
    public function __construct(
        public readonly array $testimonials,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            testimonials: $data['testimonials'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}