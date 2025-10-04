<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\RequiredRule;

class TestimonialBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'testimonial';
    }

    public function getValidationRules(): array
    {
        return [
            'testimonials' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $testimonials = [];

        foreach ($data['testimonials'] ?? [] as $testimonial) {
            if (!empty($testimonial['text']) && !empty($testimonial['author'])) {
                $testimonials[] = [
                    'text' => trim($testimonial['text']),
                    'author' => trim($testimonial['author']),
                    'role' => trim($testimonial['role'] ?? ''),
                    'rating' => (int)($testimonial['rating'] ?? 5),
                    'image' => $testimonial['image'] ?? null,
                    'formatted_text' => htmlspecialchars($testimonial['text']),
                    'formatted_author' => htmlspecialchars($testimonial['author']),
                    'formatted_role' => htmlspecialchars($testimonial['role'] ?? ''),
                    'avatar_initials' => $this->getInitials($testimonial['author'])
                ];
            }
        }

        return [
            'testimonials' => $testimonials,
            'testimonial_count' => count($testimonials),
            'layout' => $data['layout'] ?? 'grid'
        ];
    }

    private function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<section class=\"testimonials-block\">";
        $html .= "<div class=\"container\">";

        $html .= "<div class=\"testimonials-header\">";
        $html .= "<h2>What Our Clients Say</h2>";
        $html .= "<p>Don't just take our word for it - hear from satisfied clients</p>";
        $html .= "</div>";

        $html .= "<div class=\"testimonials-grid\">";

        foreach ($parsedData['testimonials'] as $testimonial) {
            // Use AwardBlockParser to generate each testimonial
            $awardParser = new AwardBlockParser();
            $testimonialData = [
                'subcategory' => 'Client Review',
                'productName' => $testimonial['author'] . ' - ' . $testimonial['role'],
                'caption' => $testimonial['text'],
                'alt' => 'Client testimonial from ' . $testimonial['author'],
                'winner' => true,
                'rating' => $testimonial['rating'],
                'strapline' => $testimonial['role'],
                'image' => $testimonial['image']
            ];
            $parsedTestimonial = $awardParser->parse($testimonialData);
            $html .= $awardParser->generateHtml($parsedTestimonial);
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}