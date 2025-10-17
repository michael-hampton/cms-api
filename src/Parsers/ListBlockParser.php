<?php

// ListBlockParser.php
namespace App\Parsers;

use App\Enums\ListType;
use App\Enums\SchemaType;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\RequiredRule;

class ListBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'list';
    }

    public function getValidationRules(): array
    {
        return [
            'listType' => [
                new RequiredRule(),
                new EnumRule(ListType::class)
            ],
            'startIndex' => [
                new IntegerRule()
            ],
            'schemaType' => [
                new EnumRule(SchemaType::class)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        $items = $this->parseItems($data['items'] ?? []);
        $listType = $data['listType'] ?? 'ul';

        return [
            'listType' => $listType,
            'startIndex' => ($listType === 'ol') ? (int)($data['startIndex'] ?? 1) : null,
            'schemaType' => $data['schemaType'] ?? 'none',
            'items' => $items,
            'item_count' => count($items),
            'total_word_count' => $this->calculateTotalWordCount($items),
            'formatted_items' => array_map(function($item) {
                return nl2br(htmlspecialchars($item));
            }, $items)
        ];
    }

    private function parseItems(array $items): array
    {
        $parsed = [];

        foreach ($items as $item) {
            $trimmed = trim($item);
            if (!empty($trimmed)) {
                $parsed[] = $trimmed;
            }
        }

        return $parsed;
    }

    private function calculateTotalWordCount(array $items): int
    {
        $totalWords = 0;

        foreach ($items as $item) {
            $totalWords += str_word_count(strip_tags($item));
        }

        return $totalWords;
    }

    public function getSupportedSchemaTypes(): array
    {
        return ['none', 'steps', 'ingredients'];
    }

    public function generateHtml(array $parsedData): string
    {
        $listTag = $parsedData['listType'] === 'ol' ? 'ol' : 'ul';
        $schemaClass = $parsedData['schemaType'] !== 'none' ? " list-schema-{$parsedData['schemaType']}" : '';

        $html = "<div class=\"list-block{$schemaClass}\">";

        $listAttrs = '';
        if ($parsedData['listType'] === 'ol' && $parsedData['startIndex'] !== 1) {
            $listAttrs = " start=\"{$parsedData['startIndex']}\"";
        }

        $html .= "<{$listTag} class=\"list-items\"{$listAttrs}>";

        foreach ($parsedData['formatted_items'] as $item) {
            $html .= "<li class=\"list-item\">{$item}</li>";
        }

        $html .= "</{$listTag}>";
        $html .= "</div>";

        return $html;
    }
}