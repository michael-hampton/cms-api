<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\AccordionBlockDto;
use App\Parsers\Renderers\AccordionBlockRenderer;

class AccordionBlockParser extends BaseBlockParser
{
    private const ALLOWED_THEMES = ['light', 'dark', 'colored', 'minimal'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];
    private const MIN_VISIBLE_ITEMS = 1;
    private const MAX_VISIBLE_ITEMS = 50;
    private const MAX_INTRO_LENGTH = 5000;

    private AccordionBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new AccordionBlockRenderer();
    }

    public function getType(): string
    {
        return 'accordion';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'introContent' => [new MaxLengthRule(self::MAX_INTRO_LENGTH)],
            'items' => [new RequiredRule(), new ArrayRule()],
            'allowMultipleOpen' => [new BooleanRule()],
            'openFirstByDefault' => [new BooleanRule()],
            'context' => [new InRule(self::ALLOWED_CONTEXTS)],
            'theme' => [new InRule(self::ALLOWED_THEMES)],
            'visibleItemsCount' => [
                new IntegerRule(),
                new MinRule(self::MIN_VISIBLE_ITEMS),
                new MaxRule(self::MAX_VISIBLE_ITEMS)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = AccordionBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = AccordionBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}