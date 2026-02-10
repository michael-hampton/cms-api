<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\NewsFeedBlockDto;
use App\Parsers\Renderers\NewsFeedBlockRenderer;

class NewsFeedBlockParser extends BaseBlockParser
{
    private NewsFeedBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new NewsFeedBlockRenderer();
    }
    public function getType(): string
    {
        return 'news-feed';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'layout' => [new InRule(['grid', 'list', 'cards', 'masonry'])],
            'columns' => [new InRule([2, 3, 4])],
            'showExcerpt' => [new BooleanRule()],
            'showDate' => [new BooleanRule()],
            'showAuthor' => [new BooleanRule()],
            'showCategory' => [new BooleanRule()],
            'showReadTime' => [new BooleanRule()],
            'items' => [new RequiredRule(), new ArrayRule()],
            'limit' => [new MinRule(1)]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = NewsFeedBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = NewsFeedBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}