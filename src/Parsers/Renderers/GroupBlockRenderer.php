<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\GroupBlockDto;
use App\Parsers\ProductBlockParser;

class GroupBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        $layout = $dto->layout ?? 'default';
        $blocks = $dto->blocks ?? [];

        if ($layout === 'spotlight') {
            return $this->generateSpotlightHtml($blocks);
        } elseif ($layout === 'carousel') {
            return $this->generateCarouselHtml($blocks, $dto);
        }

        // Default layout
        return $this->generateDefaultHtml($blocks);
    }

    private function generateSpotlightHtml(array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }

        // Find the first image block for the sticky image
        $imageBlock = null;
        $imageBlockIndex = -1;
        $productBlocks = [];

        foreach ($blocks as $index => $block) {
            if ($block['type'] === 'image' && $imageBlock === null) {
                $imageBlock = $block;
                $imageBlockIndex = $index;
            } elseif ($block['type'] === 'product') {
                $productBlocks[] = $block;
            }
        }

        // If no image block found, render as default
        if (!$imageBlock) {
            return $this->generateDefaultHtml($blocks);
        }

        $html = '<div class="group-spotlight-container" data-layout="spotlight">';

        // Desktop: sticky image on left, scrollable products on right
        $html .= '<div class="spotlight-desktop">';
        $html .= '<div class="spotlight-image-wrapper">';
        $html .= $this->renderImageBlock($imageBlock);
        $html .= '</div>';

        $html .= '<div class="spotlight-content-wrapper">';
        foreach ($blocks as $index => $block) {
            if ($index !== $imageBlockIndex) {
                $html .= $this->renderBlock($block);
            }
        }
        $html .= '</div>';
        $html .= '</div>'; // End spotlight-desktop

        // Mobile: image first, then products below
        $html .= '<div class="spotlight-mobile">';
        $html .= '<div class="spotlight-mobile-image">';
        $html .= $this->renderImageBlock($imageBlock);
        $html .= '</div>';

        $html .= '<div class="spotlight-mobile-content">';
        foreach ($blocks as $index => $block) {
            if ($index !== $imageBlockIndex) {
                $html .= $this->renderBlock($block);
            }
        }
        $html .= '</div>';
        $html .= '</div>'; // End spotlight-mobile

        $html .= '</div>'; // End group-spotlight-container

        return $html;
    }

    private function generateDefaultHtml(array $blocks): string
    {
        $html = '<div class="group-default-container" data-layout="default">';

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'unknown';

        switch ($type) {
            case 'image':
                return $this->renderImageBlock($block);
            case 'product':
                $productBlockParser = new ProductBlockParser();
                $parsedProduct = $productBlockParser->parse($block);
                return $productBlockParser->generateHtml($parsedProduct);
            default:
                return '';
        }
    }

    private function renderImageBlock(array $imageBlock): string
    {
        $src = htmlspecialchars($imageBlock['src'] ?? '');
        $alt = htmlspecialchars($imageBlock['alt'] ?? '');
        $caption = htmlspecialchars($imageBlock['caption'] ?? '');

        $html = '<figure class="spotlight-image">';
        $html .= '<img src="' . $src . '" alt="' . $alt . '" loading="lazy">';

        if (!empty($caption)) {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        }

        $html .= '</figure>';

        return $html;
    }

    private function generateCarouselHtml(array $blocks, GroupBlockDto $dto): string
    {
        $carouselTitle = $this->escape($dto->carouselTitle) ?? '';

        $html = '<div class="group-carousel-container" data-layout="carousel">';

        if (!empty($carouselTitle)) {
            $html .= '<h3 class="carousel-title">' . $carouselTitle . '</h3>';
        }

        $html .= '<div class="carousel-wrapper">';
        $html .= '<div class="carousel-track">';

        foreach ($blocks as $block) {
            $html .= '<div class="carousel-item">';
            $html .= $this->renderBlock($block);
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<button class="carousel-nav carousel-prev" aria-label="Previous">&lt;</button>';
        $html .= '<button class="carousel-nav carousel-next" aria-label="Next">&gt;</button>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'group';
    }
}