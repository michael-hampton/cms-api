<?php

namespace App\Parsers;

use App\Enums\Alignment;
use App\Enums\Blocks\ImageLayout;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\ImageBlockDto;
use App\Parsers\Renderers\ImageBlockRenderer;
use App\Validation\Custom\AltTextLengthRule;
use App\Validation\Custom\ImageSourceRule;

class ImageBlockParser extends BaseBlockParser
{
    private ImageBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ImageBlockRenderer();
    }
    public function getType(): string
    {
        return 'image';
    }

    public function getValidationRules(): array
    {
        return [
            'src' => [
                new RequiredRule(),
                new ImageSourceRule()
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'alt' => [
                new RequiredRule(),
                new AltTextLengthRule()
            ],
            'linkUrl' => [
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
            'layout' => [
                new EnumRule(ImageLayout::class)
            ],
            'alignment' => [
                new EnumRule(Alignment::class)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ImageBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = ImageBlockDto::fromArray($parsedData);

        return $this->renderer->render($dto);
    }
}