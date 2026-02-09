<?php

namespace App\Parsers\Renderers;

use App\Parsers\AwardBlockParser;
use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\TestimonialBlockDto;

class TestimonialBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'testimonial';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof TestimonialBlockDto) {
            return '';
        }

        if ($dto->layout === 'carousel') {
            return $this->renderCarousel($dto);
        }

        return $this->renderBlock($dto);
    }

    private function renderBlock(TestimonialBlockDto $dto): string
    {
        $html = "<section class=\"testimonials-block\">";
        $html .= "<div class=\"container\">";

        $html .= "<div class=\"testimonials-header\">";
        $html .= "<h2>What Our Clients Say</h2>";
        $html .= "<p>Don't just take our word for it - hear from satisfied clients</p>";
        $html .= "</div>";

        $html .= "<div class=\"testimonials-grid\">";

        foreach ($dto->testimonials as $testimonial) {
            $html .= $this->renderTestimonialAsAward($testimonial);
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function renderCarousel(TestimonialBlockDto $dto): string
    {
        $html = "<div class=\"testimonial-block\">";
        $html .= "<div class=\"testimonial-carousel\">";

        $html .= "<div class=\"testimonial-nav prev\">";
        $html .= "<button class=\"testimonial-nav-btn\" onclick=\"scrollTestimonials(this, 'prev')\" aria-label=\"Previous\">&larr;</button>";
        $html .= "</div>";

        $html .= "<div class=\"testimonial-nav next\">";
        $html .= "<button class=\"testimonial-nav-btn\" onclick=\"scrollTestimonials(this, 'next')\" aria-label=\"Next\">&rarr;</button>";
        $html .= "</div>";

        $html .= "<div class=\"testimonial-carousel-wrapper\">";
        $html .= "<div class=\"testimonial-carousel-track\" data-testimonial-track>";

        foreach ($dto->testimonials as $testimonial) {
            $html .= $this->renderTestimonialItem($testimonial);
        }

        $html .= "</div>";
        $html .= "</div>";

        if (count($dto->testimonials) > 1) {
            $html .= "<div class=\"testimonial-indicators\">";
            for ($i = 0; $i < count($dto->testimonials); $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"testimonial-indicator{$activeClass}\" onclick=\"scrollTestimonialsToIndex(this, {$i})\" aria-label=\"Go to testimonial " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderTestimonialAsAward(array $testimonial): string
    {
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
        return $awardParser->generateHtml($parsedTestimonial);
    }

    private function renderTestimonialItem(array $testimonial): string
    {
        $html = "<div class=\"testimonial-item\">";

        if (!empty($testimonial['image'])) {
            $html .= "<div class=\"testimonial-image\">";
            $html .= "<img src=\"{$this->escape($testimonial['image']['src'])}\" alt=\"{$this->escape($testimonial['author'])}\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"testimonial-content\">";
        $html .= "<p class=\"testimonial-text\">{$this->escape($testimonial['text'])}</p>";
        $html .= "<div class=\"testimonial-rating\">{$testimonial['rating']}</div>";
        $html .= "<p class=\"testimonial-author\">{$this->escape($testimonial['author'])}</p>";

        if (!empty($testimonial['role'])) {
            $html .= "<p class=\"testimonial-role\">{$this->escape($testimonial['role'])}</p>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}