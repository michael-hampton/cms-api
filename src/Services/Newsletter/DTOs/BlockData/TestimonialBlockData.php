<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TestimonialBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $layout,
        public readonly array $testimonials,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            title: $data['title'] ?? '',
            subtitle: $data['subtitle'] ?? '',
            layout: $data['layout'] ?? 'grid',
            testimonials: $data['testimonials'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}