<?php

namespace App\Parsers;

use App\Enums\Blocks\ListType;
use App\Enums\Blocks\SchemaType;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\ListBlockDto;
use App\Parsers\Renderers\ListBlockRenderer;

class ListBlockParser extends BaseBlockParser
{
    private ListBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ListBlockRenderer();
    }
    public function getType(): string
    {
        return 'list';
    }

    public function getValidationRules(): array
    {
        return [
            'listType' => [
                new RequiredRule(),
                new EnumRule(ListType::class)
            ],
            'startIndex' => [
                new IntegerRule()
            ],
            'schemaType' => [
                new EnumRule(SchemaType::class)
            ],
            'items' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ListBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = ListBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}