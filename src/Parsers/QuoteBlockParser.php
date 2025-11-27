<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class QuoteBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'quote';
    }

    public function getValidationRules(): array
    {
        return [
            'text' => [
                new RequiredRule(),
                new MaxLengthRule(1000)
            ],
            'attribution' => [
                new MaxLengthRule(255)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $text = trim($data['text'] ?? '');
        $attribution = trim($data['attribution'] ?? '');

        return [
            'text' => $text,
            'attribution' => $attribution,
            'word_count' => str_word_count($text),
            'formatted_text' => nl2br(htmlspecialchars($text)),
            'formatted_attribution' => htmlspecialchars($attribution),
            'has_attribution' => !empty($attribution),
            'context' => $data['context'] ?? 'default',
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $contextClass = $parsedData['context'] === 'sidebar' ? ' quote-sidebar' : '';
        $html = "<blockquote class=\"quote-block{$contextClass}\">";

        $html .= "<div class=\"quote-text\">{$parsedData['formatted_text']}</div>";

        if ($parsedData['has_attribution']) {
            $html .= "<cite class=\"quote-attribution\">{$parsedData['formatted_attribution']}</cite>";
        }

        $html .= "</blockquote>";

        return $html;
    }
}