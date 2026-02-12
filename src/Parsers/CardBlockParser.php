<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Parsers\Dtos\CardBlockDto;
use App\Parsers\Renderers\CardBlockRenderer;

class CardBlockParser extends BaseBlockParser
{
    private CardBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new CardBlockRenderer();
    }
    public function getType(): string
    {
        return 'card';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(100)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'linkUrl' => [
                new MaxLengthRule(500)
            ],
            'buttonType' => [
                new MaxLengthRule(20)
            ],
            'buttonText' => [
                new MaxLengthRule(30)
            ],
            'sponsorDeclaration.sponsoredText' => [
                new MaxLengthRule(50)
            ],
            'sponsorDeclaration.sponsorName' => [
                new MaxLengthRule(100)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = CardBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }


    public function generateHtml(array $parsedData): string
    {
        $dto = CardBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}