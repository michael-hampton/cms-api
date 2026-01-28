<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\GroupLayoutRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;

class GroupBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'group';
    }

    public function getValidationRules(): array
    {
        return [
            'layout' => [
                new RequiredRule(),
                //new GroupLayoutRule()
            ],
            'blocks' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'carouselTitle' => [
                new RequiredIfRule('layout', 'carousel'),
                new MaxLengthRule(200)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $layout = $data['layout'] ?? 'default';
        $blocks = $data['blocks'] ?? [];
        $carouselTitle = trim($data['carouselTitle'] ?? '');

        return [
            'layout' => $layout,
            'blocks' => $blocks,
            'carouselTitle' => $carouselTitle,
            'block_count' => count($blocks),
            'layout_display' => $this->getLayoutDisplayName($layout),
            'has_carousel_title' => !empty($carouselTitle),
            'is_carousel' => $layout === 'carousel',
            'is_spotlight' => $layout === 'spotlight',
            'is_default' => $layout === 'default',
            'carousel_title_word_count' => str_word_count($carouselTitle),
            'formatted_carousel_title' => htmlspecialchars($carouselTitle, ENT_QUOTES, 'UTF-8'),
            'css_layout_class' => $this->getLayoutCssClass($layout),
            'supports_title' => $this->supportsTitle($layout),
            'max_recommended_blocks' => $this->getMaxRecommendedBlocks($layout),
            'block_ids' => $this->extractBlockIds($blocks)
        ];
    }

    private function getLayoutDisplayName(string $layout): string
    {
        $displayNames = [
            'default' => 'Default Layout',
            'carousel' => 'Carousel Layout',
            'spotlight' => 'Spotlight Layout',
            'grid' => 'Grid Layout',
            'masonry' => 'Masonry Layout'
        ];

        return $displayNames[$layout] ?? 'Default Layout';
    }

    private function getLayoutCssClass(string $layout): string
    {
        return 'group-layout-' . $layout;
    }

    private function supportsTitle(string $layout): bool
    {
        return in_array($layout, ['carousel', 'spotlight']);
    }

    private function getMaxRecommendedBlocks(string $layout): int
    {
        $limits = [
            'default' => 50,
            'carousel' => 10,
            'spotlight' => 5,
            'grid' => 20,
            'masonry' => 30
        ];

        return $limits[$layout] ?? 50;
    }

    private function extractBlockIds(array $blocks): array
    {
        return array_map(function($block) {
            return $block['id'] ?? null;
        }, $blocks);
    }

    public function validateBlockStructure(array $blocks): array
    {
        $errors = [];

        foreach ($blocks as $index => $block) {
            if (!isset($block['id']) || empty($block['id'])) {
                $errors[] = "Block at index {$index} is missing required 'id' field";
            }

            if (!isset($block['type']) || empty($block['type'])) {
                $errors[] = "Block at index {$index} is missing required 'type' field";
            }
        }

        return $errors;
    }

    public function calculateGroupMetrics(array $data): array
    {
        $blocks = $data['blocks'] ?? [];
        $blockTypes = array_count_values(array_column($blocks, 'type'));

        return [
            'total_nested_blocks' => count($blocks),
            'block_types_distribution' => $blockTypes,
            'unique_block_types' => array_keys($blockTypes),
            'most_common_block_type' => !empty($blockTypes) ? array_keys($blockTypes, max($blockTypes))[0] : null,
            'nesting_depth' => $this->calculateNestingDepth($blocks),
            'estimated_render_time_ms' => $this->estimateRenderTime($blocks)
        ];
    }

    private function calculateNestingDepth(array $blocks, int $currentDepth = 1): int
    {
        $maxDepth = $currentDepth;

        foreach ($blocks as $block) {
            if ($block['type'] === 'group' && isset($block['blocks'])) {
                $nestedDepth = $this->calculateNestingDepth($block['blocks'], $currentDepth + 1);
                $maxDepth = max($maxDepth, $nestedDepth);
            }
        }

        return $maxDepth;
    }

    private function estimateRenderTime(array $blocks): int
    {
        // Base time per block in milliseconds
        $baseTimePerBlock = 5;
        $complexBlockMultiplier = 2;
        $complexTypes = ['code', 'group', 'wysiwyg'];

        $totalTime = 0;

        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? 'unknown';
            $multiplier = in_array($blockType, $complexTypes) ? $complexBlockMultiplier : 1;
            $totalTime += $baseTimePerBlock * $multiplier;
        }

        return $totalTime;
    }

    public function generateHtml(array $parsedData): string
    {
        $layout = $parsedData['layout'] ?? 'default';
        $blocks = $parsedData['blocks'] ?? [];

        if ($layout === 'spotlight') {
            return $this->generateSpotlightHtml($blocks, $parsedData);
        } elseif ($layout === 'carousel') {
            return $this->generateCarouselHtml($blocks, $parsedData);
        }

        // Default layout
        return $this->generateDefaultHtml($blocks);
    }

    private function generateSpotlightHtml(array $blocks, array $parsedData): string
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

    private function generateCarouselHtml(array $blocks, array $parsedData): string
    {
        $carouselTitle = $parsedData['formatted_carousel_title'] ?? '';

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
                $data = $productBlockParser->parse($block);
                return $productBlockParser->generateHtml($data);
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

    public function validateSpotlightLayout(array $blocks): array
    {
        $errors = [];

        // Check for at least one image block
        $hasImageBlock = false;
        foreach ($blocks as $block) {
            if ($block['type'] === 'image') {
                $hasImageBlock = true;
                break;
            }
        }

        if (!$hasImageBlock) {
            $errors[] = 'Spotlight layout requires at least one image block';
        }

        // Recommended: 1 image + 2-5 product blocks
        $productCount = count(array_filter($blocks, function ($block) {
            return $block['type'] === 'product';
        }));

        if ($productCount === 0) {
            $errors[] = 'Spotlight layout works best with product blocks';
        } elseif ($productCount > 5) {
            $errors[] = 'Spotlight layout recommended maximum is 5 product blocks';
        }

        return $errors;
    }

    public function extractSpotlightData(array $blocks): array
    {
        $imageBlock = null;
        $contentBlocks = [];

        foreach ($blocks as $block) {
            if ($block['type'] === 'image' && $imageBlock === null) {
                $imageBlock = $block;
            } else {
                $contentBlocks[] = $block;
            }
        }

        return [
            'image_block' => $imageBlock,
            'content_blocks' => $contentBlocks,
            'has_image' => $imageBlock !== null,
            'content_block_count' => count($contentBlocks)
        ];
    }
}