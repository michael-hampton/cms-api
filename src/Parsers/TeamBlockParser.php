<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\TeamBlockDto;
use App\Parsers\Renderers\TeamBlockRenderer;

class TeamBlockParser extends BaseBlockParser
{
    private TeamBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TeamBlockRenderer();
    }
    public function getType(): string
    {
        return 'team';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'members' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = TeamBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = TeamBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}