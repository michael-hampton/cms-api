<?php
namespace App\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\VideoBlockDto;
use App\Parsers\Renderers\VideoBlockRenderer;

class VideoBlockParser extends BaseBlockParser
{
    private VideoBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new VideoBlockRenderer();
    }
    public function getType(): string
    {
        return 'video';
    }

    public function getValidationRules(): array
    {
        return [
            'url' => [
                new RequiredRule(),
                new UrlRule(),
            ],
            'preview' => [
                new MaxLengthRule(500)
            ],
            'caption' => [
                new MaxLengthRule(500)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = VideoBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = VideoBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}