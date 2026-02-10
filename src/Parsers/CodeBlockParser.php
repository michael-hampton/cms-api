<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\CodeBlockDto;
use App\Parsers\Renderers\CodeBlockRenderer;
use App\Validation\Custom\ProgrammingLanguageRule;

class CodeBlockParser extends BaseBlockParser
{
    private CodeBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new CodeBlockRenderer();
    }
    public function getType(): string
    {
        return 'code';
    }

    public function getValidationRules(): array
    {
        return [
            'language' => [
                new RequiredRule(),
                new ProgrammingLanguageRule()
            ],
            'code' => [
                new RequiredRule(),
                new MaxLengthRule(50000) // 50KB max code length
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = CodeBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = CodeBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}