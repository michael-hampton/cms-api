<?php

namespace App\Parsers;

use App\Enums\InfoType;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class InfoBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'info';
    }

    public function getValidationRules(): array
    {
        return [
            'infoType' => [
                new RequiredRule(),
                new EnumRule(InfoType::class)
            ],
            'description' => [
                new RequiredRule(),
                new MaxLengthRule(2000)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $description = trim($data['description'] ?? '');

        return [
            'infoType' => $data['infoType'] ?? 'info',
            'description' => $description,
            'formatted_description' => nl2br(htmlspecialchars($description)),
            'word_count' => str_word_count($description),
            'icon' => $this->getInfoTypeIcon($data['infoType'] ?? 'info'),
            'context' => $data['context'] ?? 'default',
        ];
    }

    private function getInfoTypeIcon(string $infoType): string
    {
        $icons = [
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'tip' => '💡',
            'note' => '📝',
            'ingredients' => '🥗',
            'recipe' => '👨‍🍳',
            'instructions' => '📋',
            'update' => '📝',
        ];

        return $icons[$infoType] ?? 'ℹ️';
    }

    public function generateHtml(array $parsedData): string
    {
        $contextClass = $parsedData['context'] === 'sidebar' ? ' info-sidebar' : '';
        $html = "<div class=\"info-block info-type-{$parsedData['infoType']}{$contextClass}\">";

        $html .= "<div class=\"info-header\">";
        $html .= "<span class=\"info-icon\">{$parsedData['icon']}</span>";
        $html .= "<span class=\"info-type\">" . ucfirst($parsedData['infoType']) . "</span>";
        $html .= "</div>";

        $html .= "<div class=\"info-content\">";
        $html .= $parsedData['formatted_description'];
        $html .= "</div>";

        $html .= "</div>";

        return $html;
    }
}