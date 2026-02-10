<?php

namespace App\Parsers;

use App\Enums\Currency;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\DealBlockDto;
use App\Parsers\Renderers\DealBlockRenderer;
use App\Validation\Custom\SalePriceValidatorRule;

class DealBlockParser extends BaseBlockParser
{
    private DealBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new DealBlockRenderer();
    }
    public function getType(): string
    {
        return 'deal';
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
            'title' => [
                new RequiredRule(),
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
            'image' => [
                new ArrayRule()
            ],
            'currency' => [
                new RequiredRule(),
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
           'savingMode' => [
               new MaxLengthRule(20)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'showDealButton' => [
               new BooleanRule()
            ],
            'starBlock' => [
                new BooleanRule()
            ],
            'voucherId' => [
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
        $dto = DealBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = DealBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}