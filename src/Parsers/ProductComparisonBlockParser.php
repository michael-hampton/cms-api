<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class ProductComparisonBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'product-comparison';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productA' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productB' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'comparisons' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $comparisons = $this->parseComparisons($data['comparisons'] ?? []);

        return [
            'title' => trim($data['title'] ?? ''),
            'productA' => trim($data['productA'] ?? ''),
            'productB' => trim($data['productB'] ?? ''),
            'comparisons' => $comparisons,
            'comparison_count' => count($comparisons),
            'total_word_count' => $this->calculateTotalWordCount($comparisons)
        ];
    }

    private function parseComparisons(array $comparisons): array
    {
        $parsed = [];

        foreach ($comparisons as $comparison) {
            if (!is_array($comparison)) {
                continue;
            }

            $subtitle = trim($comparison['subtitle'] ?? '');
            $items = $comparison['items'] ?? [];

            if (empty($subtitle) || !is_array($items) || count($items) < 2) {
                continue;
            }

            $parsedComparison = [
                'subtitle' => $subtitle,
                'items' => $this->parseComparisonItems($items),
                'word_count' => str_word_count($subtitle)
            ];

            $parsed[] = $parsedComparison;
        }

        return $parsed;
    }

    private function parseComparisonItems(array $items): array
    {
        $parsed = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['value'])) {
                $value = trim($item['value']);
            } else {
                $value = trim((string)$item);
            }

            $parsed[] = [
                'value' => $value,
                'formatted_value' => htmlspecialchars($value),
                'word_count' => str_word_count($value)
            ];
        }

        // Ensure we always have exactly 2 items
        while (count($parsed) < 2) {
            $parsed[] = [
                'value' => '',
                'formatted_value' => '',
                'word_count' => 0
            ];
        }

        return array_slice($parsed, 0, 2);
    }

    private function calculateTotalWordCount(array $comparisons): int
    {
        $totalWords = 0;

        foreach ($comparisons as $comparison) {
            $totalWords += $comparison['word_count'] ?? 0;

            foreach ($comparison['items'] ?? [] as $item) {
                $totalWords += $item['word_count'] ?? 0;
            }
        }

        return $totalWords;
    }

    public function getComparisonValidationRules(): array
    {
        return [
            'subtitle' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"product-comparison-block\">";

        $html .= "<h3 class=\"comparison-title\">{$parsedData['title']}</h3>";

        $html .= "<div class=\"comparison-table\">";
        $html .= "<div class=\"comparison-header\">";
        $html .= "<div class=\"comparison-header-cell\"></div>";
        $html .= "<div class=\"comparison-header-cell product-a\">{$parsedData['productA']}</div>";
        $html .= "<div class=\"comparison-header-cell product-b\">{$parsedData['productB']}</div>";
        $html .= "</div>";

        foreach ($parsedData['comparisons'] as $comparison) {
            $html .= "<div class=\"comparison-row\">";
            $html .= "<div class=\"comparison-label\">{$comparison['subtitle']}</div>";

            foreach ($comparison['items'] as $item) {
                $html .= "<div class=\"comparison-value\">{$item['formatted_value']}</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}