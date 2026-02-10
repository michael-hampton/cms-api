<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\ServicesBlockDto;
use App\Parsers\Renderers\ServicesBlockRenderer;

class ServicesBlockParser extends BaseBlockParser
{
    private ServicesBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ServicesBlockRenderer();
    }
    public function getType(): string
    {
        return 'services';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'services' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ServicesBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = ServicesBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}