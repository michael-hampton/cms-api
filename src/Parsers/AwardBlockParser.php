<?php

// AwardBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\AwardBlockDto;
use App\Parsers\Renderers\AwardBlockRenderer;

class AwardBlockParser extends BaseBlockParser
{
    private AwardBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new AwardBlockRenderer();
    }

    public function getType(): string
    {
        return 'award';
    }

    public function getValidationRules(): array
    {
        return [
            'subcategory' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'productName' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'image' => [
                new ArrayRule()
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'alt' => [
               // new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'winner' => [
                new BooleanRule()
            ],
            'strapline' => [
                new MaxLengthRule(500)
            ],
            'rating' => [
                new MinRule(0),
                new MaxRule(5)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = AwardBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();

    }

    public function generateHtml(array $parsedData): string
    {
        $dto = AwardBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}
