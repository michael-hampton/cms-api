<?php

namespace App\Parsers;

use App\Enums\Blocks\InfoType;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\InfoBlockDto;
use App\Parsers\Renderers\InfoBlockRenderer;

class InfoBlockParser extends BaseBlockParser
{
    private InfoBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new InfoBlockRenderer();
    }
    public function getType(): string
    {
        return 'info';
    }

    public function getValidationRules(): array
    {
        return [
            'infoType' => [
                new RequiredRule(),
                new EnumRule(InfoType::class)
            ],
            'description' => [
                new RequiredRule(),
                new MaxLengthRule(2000)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = InfoBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = InfoBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}