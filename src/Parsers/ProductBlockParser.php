<?php

namespace App\Parsers;

use App\Enums\Blocks\DisplayAs;
use App\Enums\Blocks\Layout;
use App\Enums\Currency;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\ProductBlockDto;
use App\Parsers\Renderers\ProductBlockRenderer;
use App\Validation\Custom\SalePriceValidatorRule;

class ProductBlockParser extends BaseBlockParser
{
    private ProductBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ProductBlockRenderer();
    }
    public function getType(): string
    {
        return 'product';
    }

    public function getValidationRules(): array
    {
        return [
            'link' => [
                new RequiredRule(),
                new UrlRule()
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
                new BooleanRule()
            ],
            'openInNewTab' => [
                new BooleanRule()
            ],
            'displayAs' => [
                new EnumRule(DisplayAs::class)
            ],
            'linkText' => [
                new MaxLengthRule(100)
            ],
            'image' => [
                new ArrayRule()
            ],
            'name' => [
                new RequiredRule(),
                new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'brand' => [
                new MaxLengthRule(255)
            ],
            'productName' => [
                new RequiredRule(),
                new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'currency' => [
                new EnumRule(Currency::class)
            ],
            'price' => [
                new RequiredRule(),
                new MinRule(0.01)
            ],
            'salePrice' => [
                new MinRule(0),
                new SalePriceValidatorRule()
            ],
            'layout' => [
                new EnumRule(Layout::class)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ProductBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = ProductBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}