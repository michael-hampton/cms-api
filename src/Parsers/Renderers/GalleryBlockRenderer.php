<?php

namespace App\Parsers\Renderers;

use App\Enums\Blocks\GalleryLayout;
use App\Parsers\Dtos\BlockDtoInterface;

class GalleryBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if ($dto->layout === GalleryLayout::CAROUSEL->value) {
            return $this->generateCarouselLayout($dto);
        }

        if ($dto->layout === GalleryLayout::LIST->value) {
            return $this->generateListLayout($dto);
        }

        return $this->generateGridLayout($dto);
    }

    private function generateCarouselLayout(BlockDtoInterface $dto): string
    {
        $html = "<div class=\"gallery-block gallery-carousel\">";

        $html .= "<div class=\"carousel-container\">";
        $html .= "<div class=\"carousel-slides\" id=\"carousel-{$this->generateId()}\">";

        foreach ($dto->slides as $index => $slide) {
            $activeClass = $index === 0 ? ' active' : '';
            $html .= "<div class=\"carousel-slide{$activeClass}\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                $html .= "<div class=\"slide-image\">";
                $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$this->escape($slide['title'])}\" class=\"carousel-image\">";
                $html .= "</div>";
            }

            if (!empty($slide['title']) || !empty($slide['description'])) {
                $html .= "<div class=\"slide-content\">";
                if (!empty($slide['title'])) {
                    $html .= "<h3 class=\"slide-title\">{$this->escape($slide['title'])}</h3>";
                }
                if (!empty($slide['description'])) {
                    $html .= "<p class=\"slide-description\">{$this->escape($slide['description'])}</p>";
                }
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";

        // Carousel controls
        if (count($dto->slides) > 1) {
            $html .= "<button class=\"carousel-btn carousel-prev\" onclick=\"prevSlide(this)\">&larr;</button>";
            $html .= "<button class=\"carousel-btn carousel-next\" onclick=\"nextSlide(this)\">&rarr;</button>";

            // Indicators
            $html .= "<div class=\"carousel-indicators\">";
            foreach ($dto->slides as $index => $slide) {
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

    private function generateId(): string
    {
        return uniqid('carousel-');
    }

    private function generateListLayout(BlockDtoInterface $dto): string
    {
        $html = "<div class=\"gallery-block gallery-list\">";

        foreach ($dto->slides as $index => $slide) {
            $html .= "<div class=\"gallery-list-item\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                $html .= "<div class=\"list-item-image\">";

                if ($slide['link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>";
                }

                if (!empty($slide['image']['src'])) {
                    $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$this->escape($slide['title'])}\" class=\"list-image\">";
                }

                if ($slide['link']) {
                    $html .= "</a>";
                }

                $html .= "</div>";
            }

            $html .= "<div class=\"list-item-content\">";

            if (!empty($slide['title'])) {
                $html .= "<h3 class=\"list-item-title\">";
                if ($slide['link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>{$this->escape($slide['title'])}</a>";
                } else {
                    $html .= $this->escape($slide['title']);
                }
                $html .= "</h3>";
            }

            if (!empty($slide['description'])) {
                $html .= "<div class=\"list-item-description\">{$this->escapeWithBreaks($slide['description'])}</div>";
            }

            if (!empty($slide['caption'])) {
                $html .= "<div class=\"list-item-caption\">{$this->escape($slide['caption'])}</div>";
            }

            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function generateGridLayout(BlockDtoInterface $dto): string
    {
        $html = "<div class=\"gallery-block gallery-grid\">";

        $html .= "<div class=\"gallery-slides\">";

        foreach ($dto->slides as $index => $slide) {
            $html .= "<div class=\"gallery-slide\" data-slide=\"{$index}\">";

            if (!empty($slide['image'])) {
                if ($slide['link']) {
                    $linkAttrs = '';
                    if ($slide['noFollow']) $linkAttrs .= ' rel="nofollow"';
                    if ($slide['sponsored']) $linkAttrs .= ' rel="sponsored"';
                    if ($slide['openInNewTab']) $linkAttrs .= ' target="_blank"';

                    $html .= "<a href=\"{$slide['link']}\"{$linkAttrs}>";
                }

                if (!empty($slide['image']['src'])) {
                    $html .= "<img src=\"{$slide['image']['src']}\" alt=\"{$this->escape($slide['title'])}\" class=\"gallery-image\">";
                }

                if ($slide['link']) {
                    $html .= "</a>";
                }
            }

            if (!empty($slide['title']) || !empty($slide['description']) || !empty($slide['caption'])) {
                $html .= "<div class=\"gallery-slide-content\">";

                if (!empty($slide['title'])) {
                    $html .= "<h3 class=\"gallery-slide-title\">{$this->escape($slide['title'])}</h3>";
                }

                if (!empty($slide['description'])) {
                    $html .= "<div class=\"gallery-slide-description\">{$this->escape($slide['description'])}</div>";
                }

                if (!empty($slide['caption'])) {
                    $html .= "<div class=\"gallery-slide-caption\">{$this->escape($slide['caption'])}</div>";
                }

                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'gallery';
    }
}