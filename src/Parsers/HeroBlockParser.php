<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\HeroBlockDto;
use App\Parsers\Renderers\HeroBlockRenderer;

class HeroBlockParser extends BaseBlockParser
{
    private HeroBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new HeroBlockRenderer();
    }
    public function getType(): string
    {
        return 'hero';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'backgroundImage' => [new MaxLengthRule(500)],
            'ctaText' => [new MaxLengthRule(50)],
            'ctaUrl' => [new MaxLengthRule(500)],
            'secondaryCtaText' => [new MaxLengthRule(50)],
            'secondaryCtaUrl' => [new MaxLengthRule(500)],
            'showSearch' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = HeroBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData, ?int $pageId = null): string
    {
        $dto = HeroBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}