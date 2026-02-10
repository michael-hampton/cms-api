<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\PageLinksBlockDto;
use App\Parsers\Renderers\PageLinksBlockRenderer;

class PageLinksBlockParser extends BaseBlockParser
{
    private PageLinksBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new PagelinksBlockRenderer();
    }
    public function getType(): string
    {
        return 'page-links';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'layout' => [new InRule(['grid', 'list', 'compact'])],
            'columns' => [new InRule([2, 3, 4, 5])],
            'showImages' => [new BooleanRule()],
            'showDescriptions' => [new BooleanRule()],
            'links' => [new RequiredRule(), new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = PageLinksBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = PageLinksBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}