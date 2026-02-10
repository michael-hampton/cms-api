<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\CtaBlockDto;
use App\Parsers\Renderers\CtaBlockRenderer;

class CtaBlockParser extends BaseBlockParser
{
    private CtaBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new CtaBlockRenderer();
    }
    public function getType(): string
    {
        return 'cta';
    }

    public function getValidationRules(): array
    {
        return [
            'text' => [
                new RequiredRule(),
                new MaxLengthRule(100)
            ],
            'url' => [
                new RequiredRule(),
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
            'style' => [
                new MaxLengthRule(20)
            ],
            'size' => [
                new MaxLengthRule(20)
            ],
            'alignment' => [
                new MaxLengthRule(20)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = CtaBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = CtaBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}