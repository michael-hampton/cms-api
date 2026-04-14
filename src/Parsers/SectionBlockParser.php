<?php
namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\SectionBlockDto;
use App\Parsers\Renderers\SectionBlockRenderer;

class SectionBlockParser extends BaseBlockParser
{
    private SectionBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new SectionBlockRenderer();
    }
    public function getType(): string
    {
        return 'section';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'headingType' => [
                //new RequiredRule(),
                new MaxLengthRule(10)
            ],
            'navigationText' => [
                //new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'excludeFromNav' => [
                new BooleanRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = SectionBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = SectionBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}