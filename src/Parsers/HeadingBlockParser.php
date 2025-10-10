<?php

// HeadingBlockParser.php
namespace App\Parsers;

use App\Enums\HeadingLevel;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class HeadingBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'heading';
    }

    public function beforeValidation(array $data): array
    {
        if (is_int($data['level'])) {
            $data['level'] = 'h' . $data['level'];
        }

        return $data;
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
                new EnumRule(HeadingLevel::class)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $text = trim($data['text'] ?? '');
        $subtitle = trim($data['subtitle'] ?? '');
        $levelEnum = $this->getLevel($data);

        return [
            'text' => $text,
            'subtitle' => $subtitle,
            'level' => $levelEnum->getLevel(),
            'word_count' => str_word_count($text . ' ' . $subtitle),
            'formatted_text' => htmlspecialchars($text),
            'formatted_subtitle' => htmlspecialchars($subtitle),
            'has_subtitle' => !empty($subtitle)
        ];
    }

    private function getLevel(array $data): HeadingLevel
    {
        $levelInput = $data['level'] ?? 2; // default to 2

        if (is_int($levelInput)) {
            $levelValue = 'h' . $levelInput; // 3 -> 'h3'
        } elseif (is_string($levelInput) && preg_match('/^h[1-6]$/', $levelInput)) {
            $levelValue = $levelInput;
        } else {
            $levelValue = 'h2'; // fallback default
        }

        return HeadingLevel::from($levelValue);
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