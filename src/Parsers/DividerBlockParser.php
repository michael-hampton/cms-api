<?php

namespace App\Parsers;

use App\Parsers\Dtos\DividerBlockDto;
use App\Parsers\Renderers\DividerBlockRenderer;
use App\Validation\Custom\DividerStyleRule;

class DividerBlockParser extends BaseBlockParser
{
    private DividerBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new DividerBlockRenderer();
    }
    public function getType(): string
    {
        return 'divider';
    }

    public function getValidationRules(): array
    {
        return [
            'style' => [
                new DividerStyleRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = DividerBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = DividerBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}