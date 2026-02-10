<?php

namespace App\Parsers;

use App\Enums\Alignment;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\BoxoutBlockDto;
use App\Parsers\Renderers\BoxoutBlockRenderer;

class BoxoutBlockParser extends BaseBlockParser
{
    private BoxoutBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new BoxoutBlockRenderer();
    }
    public function getType(): string
    {
        return 'note';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'paragraphs' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'image' => [
                new ArrayRule()
            ],
            'alignment' => [
                new EnumRule(Alignment::class)
            ],
            'linkUrl' => [
                new UrlRule()
            ],
            'linkText' => [
                new MaxLengthRule(100)
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
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = BoxoutBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = BoxoutBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}