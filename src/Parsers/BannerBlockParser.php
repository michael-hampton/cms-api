<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\BannerBlockDto;
use App\Parsers\Renderers\BannerBlockRenderer;

class BannerBlockParser extends BaseBlockParser
{
    private BannerBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new BannerBlockRenderer();
    }
    public function getType(): string
    {
        return 'banner';
    }

    public function getValidationRules(): array
    {
        return [
            'bannerType' => [
                new RequiredRule(),
                new InRule(['promo-header', 'review-banner', 'providers-banner'])
            ],
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'ctaText' => [new MaxLengthRule(100)],
            'backgroundColor' => [new MaxLengthRule(50)],
            'textColor' => [new MaxLengthRule(50)],
            'image' => [new ArrayRule()],
            'providers' => [new ArrayRule()],
            'rating' => [],
            'reviewCount' => [],
            'showDismiss' => [new BooleanRule()],
            'dismissible' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = BannerBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = BannerBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);

    }
}