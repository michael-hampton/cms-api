<?php

// HeadingBlockParser.php
namespace App\Parsers;

use App\Enums\Blocks\HeadingLevel;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\HeadingBlockDto;
use App\Parsers\Renderers\HeadingBlockRenderer;

class HeadingBlockParser extends BaseBlockParser
{
    private HeadingBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new HeadingBlockRenderer();
    }
    public function getType(): string
    {
        return 'heading';
    }

    public function beforeValidation(array $data): array
    {
        if (!empty($data['level']) && is_int($data['level'])) {
            $data['level'] = 'h' . $data['level'];
        }

        return $data;
    }

    public function getValidationRules(): array
    {
        return [
            'text' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'level' => [
                new RequiredRule(),
                new EnumRule(HeadingLevel::class)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = HeadingBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = HeadingBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}