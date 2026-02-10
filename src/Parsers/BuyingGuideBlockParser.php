<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\BuyingGuideBlockDto;
use App\Parsers\Renderers\BuyingGuideBlockRenderer;

class BuyingGuideBlockParser extends BaseBlockParser
{
    private BuyingGuideBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new BuyingGuideBlockRenderer();
    }
    public function getType(): string
    {
        return 'buying-guide';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'url' => [
                new UrlRule()
            ],
            'specs' => [
                new ArrayRule()
            ],
            'pros' => [
                new ArrayRule()
            ],
            'cons' => [
                new ArrayRule()
            ],
            'showReviewPanel' => [
                new BooleanRule()
            ],
            'image' => [
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = BuyingGuideBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = BuyingGuideBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}