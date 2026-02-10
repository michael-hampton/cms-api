<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\QuoteBlockDto;
use App\Parsers\Renderers\QuoteBlockRenderer;

class QuoteBlockParser extends BaseBlockParser
{
    private QuoteBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new QuoteBlockRenderer();
    }
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
        // Build DTO (handles normalization + structural validation)
        $dto = QuoteBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = QuoteBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}