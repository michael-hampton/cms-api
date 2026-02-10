<?php

namespace App\Parsers;

use App\Enums\Blocks\GalleryLayout;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\GalleryBlockDto;
use App\Parsers\Renderers\GalleryBlockRenderer;

class GalleryBlockParser extends BaseBlockParser
{
    private GalleryBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new GalleryBlockRenderer();
    }
    public function getType(): string
    {
        return 'gallery';
    }

    public function getValidationRules(): array
    {
        return [
            'layout' => [
                new RequiredRule(),
                new EnumRule(GalleryLayout::class)
            ],
            'slides' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = GalleryBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function getSlideValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'image' => [
                new ArrayRule()
            ],
            'alt' => [
                new MaxLengthRule(255)
            ],
            'link' => [
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
            ]
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = GalleryBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}