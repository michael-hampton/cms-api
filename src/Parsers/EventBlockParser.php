<?php
// App/Parsers/EventBlockParser.php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\DateRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\Dtos\EventBlockDto;
use App\Parsers\Renderers\EventBlockRenderer;

class EventBlockParser extends BaseBlockParser
{
    private EventBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new EventBlockRenderer();
    }
    public function getType(): string
    {
        return 'event';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'description' => [new MaxLengthRule(2000)],
            'startDate' => [new RequiredRule(), new DateRule()],
            'endDate' => [new DateRule()],
            'startTime' => [new MaxLengthRule(10)],
            'endTime' => [new MaxLengthRule(10)],
            'location' => [new MaxLengthRule(500)],
            'address' => [new MaxLengthRule(500)],
            'mapUrl' => [new UrlRule()],
            'ticketPrice' => [new MinRule(0)],
            'currency' => [new MaxLengthRule(10)],
            'ticketUrl' => [new UrlRule()],
            'capacity' => [new MinRule(1)],
            'organizerName' => [new MaxLengthRule(255)],
            'organizerEmail' => [new EmailRule()],
            'organizerPhone' => [new MaxLengthRule(20)],
            'category' => [new MaxLengthRule(100)],
            'image' => [new ArrayRule()],
            'showSignupForm' => [new BooleanRule()],
            'featured' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = EventBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }



    public function generateHtml(array $parsedData): string
    {
        $dto = EventBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}