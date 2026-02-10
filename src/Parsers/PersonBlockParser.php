<?php

namespace App\Parsers;

use App\Enums\Blocks\DisplayType;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\PersonBlockDto;
use App\Parsers\Renderers\PersonBlockRenderer;
use App\Validation\Custom\SocialMediaUrlRule;

class PersonBlockParser extends BaseBlockParser
{
    private PersonBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new PersonBlockRenderer();
    }
    public function getType(): string
    {
        return 'person';
    }

    public function getValidationRules(): array
    {
        return [
            'name' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'role' => [
                new MaxLengthRule(255)
            ],
            'strapline' => [
                new MaxLengthRule(500)
            ],
            'bio' => [
                new MaxLengthRule(2000)
            ],
            'enableSchema' => [
                new BooleanRule()
            ],
            'email' => [
                new EmailRule()
            ],
            'twitter' => [
                new SocialMediaUrlRule()
            ],
            'website' => [
                new UrlRule()
            ],
            'instagram' => [
                new SocialMediaUrlRule()
            ],
            'facebook' => [
                new SocialMediaUrlRule()
            ],
            'linkedin' => [
                new SocialMediaUrlRule()
            ],
            'tiktok' => [
                new SocialMediaUrlRule()
            ],
            'youtube' => [
                new SocialMediaUrlRule()
            ],
            'displayType' => [
                new EnumRule(DisplayType::class)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = PersonBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = PersonBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}