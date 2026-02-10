<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\SchemaBlockDto;
use App\Parsers\Renderers\SchemaBlockRenderer;

class SchemaBlockParser extends BaseBlockParser
{
    private SchemaBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new SchemaBlockRenderer();
    }
    public function getType(): string
    {
        return 'schema';
    }

    public function getValidationRules(): array
    {
        return [
            'schemaType' => [
                new RequiredRule(),
                new MaxLengthRule(50)
            ],
            'title' => [
                new RequiredIfRule('schemaType', 'how-to'),
                new MaxLengthRule(255)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'image' => [
                new ArrayRule()
            ],
            'question' => [
                new RequiredIfRule('schemaType', 'question'),
                new MaxLengthRule(255)
            ],
            'answer' => [
                new RequiredIfRule('schemaType', 'question'),
                new MaxLengthRule(2000)
            ],
            'expansion' => [
                new MaxLengthRule(5000)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = SchemaBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = SchemaBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}