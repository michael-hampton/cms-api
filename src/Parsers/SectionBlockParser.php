<?php

// SectionBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class SectionBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'section';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'headingType' => [
                new RequiredRule(),
                new MaxLengthRule(10)
            ],
            'navigationText' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'excludeFromNav' => [
                new BooleanRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => trim($data['title'] ?? ''),
            'headingType' => $data['headingType'] ?? 'h2',
            'navigationText' => trim($data['navigationText'] ?? ''),
            'excludeFromNav' => (bool)($data['excludeFromNav'] ?? false),
            'heading_level' => $this->getHeadingLevel($data['headingType'] ?? 'h2')
        ];
    }

    private function getHeadingLevel(string $headingType): int
    {
        return (int)str_replace('h', '', $headingType);
    }

    public function generateHtml(array $parsedData): string
    {
        $level = $parsedData['heading_level'];
        $contextClass = $parsedData['context'] === 'sidebar' ? ' section-sidebar' : '';
        $html = "<div class=\"section-block section-level-{$level}{$contextClass}\">";

        $html .= "<{$parsedData['headingType']} class=\"section-title\">{$parsedData['title']}</{$parsedData['headingType']}>";

        $html .= "</div>";

        return $html;
    }
}