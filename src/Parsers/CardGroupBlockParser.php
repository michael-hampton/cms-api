<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;

class CardGroupBlockParser extends BaseBlockParser
{
    private CardBlockParser $cardParser;

    public function __construct()
    {
        $this->cardParser = new CardBlockParser();
    }

    public function getType(): string
    {
        return 'card-group';
    }

    public function getValidationRules(): array
    {
        return [
            'itemsPerRow' => [],
            'gap' => [
                new MaxLengthRule(20)
            ],
            'cards' => []
        ];
    }

    public function parse(array $data): array
    {
        $cards = [];
        if (isset($data['cards']) && is_array($data['cards'])) {
            foreach ($data['cards'] as $card) {
                // Only include cards that have some content
                if ($this->hasCardContent($card)) {
                    $cards[] = $this->cardParser->parse($card);
                }
            }
        }

        return [
            'itemsPerRow' => $this->parseItemsPerRow($data['itemsPerRow'] ?? null),
            'gap' => $this->sanitize($data['gap'] ?? 'medium'),
            'cards' => $cards
        ];
    }

    private function hasCardContent(array $card): bool
    {
        return !empty($card['title']) ||
            !empty($card['description']) ||
            !empty($card['image']['src']);
    }

    private function parseItemsPerRow($value): int
    {
        $value = (int)$value;
        // Validate range 1-4
        if ($value < 1 || $value > 4) {
            return 3; // Default
        }
        return $value;
    }

    private function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public function generateHtml(array $parsedData): string
    {
        if (empty($parsedData['cards'])) {
            return '';
        }

        $itemsPerRow = $parsedData['itemsPerRow'] ?? 3;
        $gap = $parsedData['gap'] ?? 'medium';

        $containerClass = "card-group-block card-group-items-{$itemsPerRow} card-group-gap-{$gap}";

        $html = "<div class=\"{$containerClass}\">";
        $html .= "<div class=\"card-group-container\">";

        foreach ($parsedData['cards'] as $card) {
            $html .= "<div class=\"card-group-item\">";
            $html .= $this->cardParser->generateHtml($card);
            $html .= "</div>";
        }

        $html .= "</div>"; // card-group-container
        $html .= "</div>"; // card-group-block

        return $html;
    }
}