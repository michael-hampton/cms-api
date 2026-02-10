<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\TextBlockDto;
use App\Parsers\Renderers\TextBlockRenderer;

class TextBlockParser extends BaseBlockParser
{
    private TextBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TextBlockRenderer();
    }
    public function getType(): string
    {
        return 'text';
    }

    public function getValidationRules(): array
    {
        return [
            'paragraphs' => [
                new RequiredRule(),
                new ArrayRule(),
                new MinRule(1) // At least one paragraph required
            ],
            'paragraphs.*' => [
                new RequiredRule(),
                new MaxLengthRule(10000) // Max length per paragraph
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = TextBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = TextBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}