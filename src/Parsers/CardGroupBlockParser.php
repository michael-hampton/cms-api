<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Parsers\Dtos\CardGroupBlockDto;
use App\Parsers\Renderers\CardGroupBlockRenderer;

class CardGroupBlockParser extends BaseBlockParser
{
    private CardGroupBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new CardgroupBlockRenderer();
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
        // Build DTO (handles normalization + structural validation)
        $dto = CardGroupBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    private function hasCardContent(array $card): bool
    {
        return !empty($card['title']) ||
            !empty($card['description']) ||
            !empty($card['image']['src']);
    }


    public function generateHtml(array $parsedData): string
    {
        $dto = CardGroupBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}