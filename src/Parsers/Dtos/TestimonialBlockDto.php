<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class TestimonialBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['testimonials', 'layout'];
    private const ALLOWED_LAYOUTS = ['grid', 'list', 'carousel'];

    public function __construct(
        public array  $testimonials,
        public string $layout
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'testimonials' => [],
            'layout' => 'grid'
        ]);

        if (empty($data['testimonials']) || !is_array($data['testimonials'])) {
            throw new InvalidArgumentException('Testimonials are required');
        }

        $layout = self::validateEnum(
            $data['layout'],
            self::ALLOWED_LAYOUTS,
            'grid',
            'layout'
        );

        $testimonials = self::parseTestimonials($data['testimonials']);

        if (empty($testimonials)) {
            throw new InvalidArgumentException('At least one testimonial is required');
        }

        return new self($testimonials, $layout);
    }

    private static function parseTestimonials(array $testimonials): array
    {
        $parsed = [];

        foreach ($testimonials as $testimonial) {
            if (empty($testimonial['text']) || empty($testimonial['author'])) {
                continue;
            }

            $parsed[] = [
                'text' => trim($testimonial['text']),
                'author' => trim($testimonial['author']),
                'role' => trim($testimonial['role'] ?? ''),
                'rating' => max(0, min(5, (int)($testimonial['rating'] ?? 5))),
                'image' => $testimonial['image'] ?? null,
                'avatar_initials' => self::getInitials($testimonial['author'])
            ];
        }

        return $parsed;
    }

    private static function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }

    public function toArray(): array
    {
        return [
            'testimonials' => $this->testimonials,
            'layout' => $this->layout,
            'testimonial_count' => count($this->testimonials)
        ];
    }

    public function getType(): string
    {
        return 'testimonial';
    }
}