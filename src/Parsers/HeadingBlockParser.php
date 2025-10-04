<?php

// HeadingBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Validation\Custom\HeadingLevelRule;

class HeadingBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'heading';
    }

    public function getValidationRules(): array
    {
        return [
            'text' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'level' => [
                new RequiredRule(),
                new HeadingLevelRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $text = trim($data['text'] ?? '');
        $subtitle = trim($data['subtitle'] ?? '');

        return [
            'text' => $text,
            'subtitle' => $subtitle,
            'level' => (int)($data['level'] ?? 2),
            'word_count' => str_word_count($text . ' ' . $subtitle),
            'formatted_text' => htmlspecialchars($text),
            'formatted_subtitle' => htmlspecialchars($subtitle),
            'has_subtitle' => !empty($subtitle)
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $level = $parsedData['level'];
        $html = "<div class=\"heading-block heading-level-{$level}\">";

        $html .= "<h{$level} class=\"heading-text\">{$parsedData['formatted_text']}</h{$level}>";

        if ($parsedData['has_subtitle']) {
            $html .= "<div class=\"heading-subtitle\">{$parsedData['formatted_subtitle']}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}