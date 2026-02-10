<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\TeaserBlockDto;
use App\Parsers\Renderers\TeaserBlockRenderer;

class TeaserBlockParser extends BaseBlockParser
{
    private TeaserBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TeaserBlockRenderer();
    }
    public function getType(): string
    {
        return 'teaser';
    }

    public function getValidationRules(): array
    {
        return [
            'componentId' => [
                new MaxLengthRule(100)
            ],
            'theme' => [
                new RequiredRule(),
                new MaxLengthRule(50)
            ],
            'copy' => [
                new MaxLengthRule(5000)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'footerText' => [
                new MaxLengthRule(500)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = TeaserBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = TeaserBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}