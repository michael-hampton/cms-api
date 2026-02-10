<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\StatsBlockDto;
use App\Parsers\Renderers\StatsBlockRenderer;

class StatsBlockParser extends BaseBlockParser
{
    private StatsBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new StatsBlockRenderer();
    }
    public function getType(): string
    {
        return 'stats';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(255)
            ],
            'stats' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = StatsBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = StatsBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}