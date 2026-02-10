<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\ProductComparisonBlockDto;
use App\Parsers\Renderers\ProductComparisonBlockRenderer;

class ProductComparisonBlockParser extends BaseBlockParser
{
    private ProductComparisonBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ProductComparisonBlockRenderer();
    }
    public function getType(): string
    {
        return 'product-comparison';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productA' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productB' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'comparisons' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ProductComparisonBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function getComparisonValidationRules(): array
    {
        return [
            'subtitle' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = ProductComparisonBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}