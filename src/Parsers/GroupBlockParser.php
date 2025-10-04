<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\GroupLayoutRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredIfRule;

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
                new GroupLayoutRule()
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
       return '';
    }
}