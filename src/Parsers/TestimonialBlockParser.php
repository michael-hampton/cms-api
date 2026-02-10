<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\TestimonialBlockDto;
use App\Parsers\Renderers\TestimonialBlockRenderer;

class TestimonialBlockParser extends BaseBlockParser
{
    private TestimonialBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new TestimonialBlockRenderer();
    }
    public function getType(): string
    {
        return 'testimonial';
    }

    public function getValidationRules(): array
    {
        return [
            'testimonials' => [
                new RequiredRule(),
                new ArrayRule()
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = TestimonialBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = TestimonialBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}