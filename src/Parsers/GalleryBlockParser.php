<?php

namespace App\Parsers;

use App\Enums\GalleryLayout;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class GalleryBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'gallery';
    }

    public function getValidationRules(): array
    {
        return [
            'layout' => [
                new RequiredRule(),
                new EnumRule(GalleryLayout::class)
            ],
            'slides' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $slides = $this->parseSlides($data['slides'] ?? []);

        return [
            'layout' => $data['layout'] ?? 'carousel',
            'slides' => $slides,
            'slide_count' => count($slides),
            'total_word_count' => $this->calculateTotalWordCount($slides)
        ];
    }

    private function parseSlides(array $slides): array
    {
        $parsed = [];

        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $title = trim($slide['title'] ?? '');
            $description = trim($slide['description'] ?? '');
            $caption = trim($slide['caption'] ?? '');
            $alt = trim($slide['alt'] ?? '');

            if (empty($title) && empty($description) && empty($slide['image'])) {
                continue; // Skip empty slides
            }

            $parsedSlide = [
                'title' => $title,
                'caption' => $caption,
                'description' => $description,
                'image' => $slide['image'] ?? null,
                'alt' => $alt,
                'link' => $slide['link'] ?? null,
                'noFollow' => (bool)($slide['noFollow'] ?? false),
                'sponsored' => (bool)($slide['sponsored'] ?? false),
                'openInNewTab' => (bool)($slide['openInNewTab'] ?? false),
                'has_link' => !empty($slide['link']),
                'word_count' => str_word_count(strip_tags($title . ' ' . $description . ' ' . $caption)),
                'formatted_title' => htmlspecialchars($title),
                'formatted_description' => nl2br(htmlspecialchars($description)),
                'formatted_caption' => nl2br(htmlspecialchars($caption))
            ];

            $parsed[] = $parsedSlide;
        }

        return $parsed;
    }

    private function calculateTotalWordCount(array $slides): int
    {
        $totalWords = 0;

        foreach ($slides as $slide) {
            $totalWords += $slide['word_count'] ?? 0;
        }

        return $totalWords;
    }

    public function getSlideValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'image' => [
                new ArrayRule()
            ],
            'alt' => [
                new MaxLengthRule(255)
            ],
            'link' => [
                new UrlRule()
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
                new BooleanRule()
            ],
            'openInNewTab' => [
                new BooleanRule()
            ]
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        if ($parsedData['layout'] === 'carousel') {
            return $this->generateCarouselLayout($parsedData);
        }

        if ($parsedData['layout'] === 'list') {
            return $this->generateListLayout($parsedData);
        }

        return $this->generateGridLayout($parsedData);
    }

    private function generateCarouselLayout(array $parsedData): string
    {
        $html = "<div class=\"gallery-block gallery-carousel\">";

        $html .= "<div class=\"carousel-container\">";
        $html .= "<div class=\"carousel-slides\" id=\"carousel-{$this->generateId()}\">";

        foreach ($parsedData['slides'] as $index => $slide) {
            $activeClass = $index === 0 ? ' active' : '';
            $html .= "<div class=\"carousel-slide{$activeClass}\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                $html .= "<div class=\"slide-image\">";
                $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$slide['formatted_title']}\" class=\"carousel-image\">";
                $html .= "</div>";
            }

            if (!empty($slide['title']) || !empty($slide['description'])) {
                $html .= "<div class=\"slide-content\">";
                if (!empty($slide['title'])) {
                    $html .= "<h3 class=\"slide-title\">{$slide['formatted_title']}</h3>";
                }
                if (!empty($slide['description'])) {
                    $html .= "<p class=\"slide-description\">{$slide['formatted_description']}</p>";
                }
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";

        // Carousel controls
        if (count($parsedData['slides']) > 1) {
            $html .= "<button class=\"carousel-btn carousel-prev\" onclick=\"prevSlide(this)\">&larr;</button>";
            $html .= "<button class=\"carousel-btn carousel-next\" onclick=\"nextSlide(this)\">&rarr;</button>";

            // Indicators
            $html .= "<div class=\"carousel-indicators\">";
            foreach ($parsedData['slides'] as $index => $slide) {
                $activeClass = $index === 0 ? ' active' : '';
                $html .= "<button class=\"indicator{$activeClass}\" onclick=\"goToSlide(this, {$index})\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";

        // Add JavaScript for carousel functionality
        $html .= "<script>
        function nextSlide(btn) {
            const carousel = btn.parentElement.querySelector('.carousel-slides');
            const slides = carousel.querySelectorAll('.carousel-slide');
            const indicators = carousel.parentElement.querySelectorAll('.indicator');
            let current = 0;
            
            slides.forEach((slide, index) => {
                if (slide.classList.contains('active')) current = index;
                slide.classList.remove('active');
            });
            
            current = (current + 1) % slides.length;
            slides[current].classList.add('active');
            
            indicators.forEach((ind, index) => {
                ind.classList.toggle('active', index === current);
            });
        }
        
        function prevSlide(btn) {
            const carousel = btn.parentElement.querySelector('.carousel-slides');
            const slides = carousel.querySelectorAll('.carousel-slide');
            const indicators = carousel.parentElement.querySelectorAll('.indicator');
            let current = 0;
            
            slides.forEach((slide, index) => {
                if (slide.classList.contains('active')) current = index;
                slide.classList.remove('active');
            });
            
            current = current === 0 ? slides.length - 1 : current - 1;
            slides[current].classList.add('active');
            
            indicators.forEach((ind, index) => {
                ind.classList.toggle('active', index === current);
            });
        }
        
        function goToSlide(btn, slideIndex) {
            const carousel = btn.parentElement.parentElement.querySelector('.carousel-slides');
            const slides = carousel.querySelectorAll('.carousel-slide');
            const indicators = carousel.parentElement.querySelectorAll('.indicator');
            
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(ind => ind.classList.remove('active'));
            
            slides[slideIndex].classList.add('active');
            indicators[slideIndex].classList.add('active');
        }
        </script>";

        $html .= "</div>";

        return $html;
    }

    private function generateListLayout(array $parsedData): string
    {
        $html = "<div class=\"gallery-block gallery-list\">";

        foreach ($parsedData['slides'] as $index => $slide) {
            $html .= "<div class=\"gallery-list-item\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                $html .= "<div class=\"list-item-image\">";

                if ($slide['has_link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>";
                }

                if (!empty($slide['image']['src'])) {
                    $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$slide['formatted_title']}\" class=\"list-image\">";
                }

                if ($slide['has_link']) {
                    $html .= "</a>";
                }

                $html .= "</div>";
            }

            $html .= "<div class=\"list-item-content\">";

            if (!empty($slide['title'])) {
                $html .= "<h3 class=\"list-item-title\">";
                if ($slide['has_link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>{$slide['formatted_title']}</a>";
                } else {
                    $html .= $slide['formatted_title'];
                }
                $html .= "</h3>";
            }

            if (!empty($slide['description'])) {
                $html .= "<div class=\"list-item-description\">{$slide['formatted_description']}</div>";
            }

            if (!empty($slide['caption'])) {
                $html .= "<div class=\"list-item-caption\">{$slide['formatted_caption']}</div>";
            }

            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function generateGridLayout(array $parsedData): string
    {
        $html = "<div class=\"gallery-block gallery-grid\">";

        $html .= "<div class=\"gallery-slides\">";

        foreach ($parsedData['slides'] as $index => $slide) {
            $html .= "<div class=\"gallery-slide\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                if ($slide['has_link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>";
                }

                if (!empty($slide['image']['src'])) {
                    $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$slide['formatted_title']}\" class=\"gallery-image\">";
                }

                if ($slide['has_link']) {
                    $html .= "</a>";
                }
            }

            if (!empty($slide['title']) || !empty($slide['description']) || !empty($slide['caption'])) {
                $html .= "<div class=\"gallery-slide-content\">";

                if (!empty($slide['title'])) {
                    $html .= "<h3 class=\"gallery-slide-title\">{$slide['formatted_title']}</h3>";
                }

                if (!empty($slide['description'])) {
                    $html .= "<div class=\"gallery-slide-description\">{$slide['formatted_description']}</div>";
                }

                if (!empty($slide['caption'])) {
                    $html .= "<div class=\"gallery-slide-caption\">{$slide['formatted_caption']}</div>";
                }

                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateId(): string
    {
        return uniqid('carousel-');
    }
}