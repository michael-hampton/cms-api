<?php
namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Parsers\Dtos\TableBlockDto;
use App\Parsers\Renderers\TableBlockRenderer;

class TableBlockParser extends BaseBlockParser
{
    private TableBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TableBlockRenderer();
    }
    public function getType(): string
    {
        return 'table';
    }

    public function getValidationRules(): array
    {
        return [
            'hasHeader' => [
                new BooleanRule()
            ],
            'rows' => [
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = TableBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = TableBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}