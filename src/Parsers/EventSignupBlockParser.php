<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\EventSignupBlockDto;
use App\Parsers\Renderers\EventSignupBlockRenderer;

class EventSignupBlockParser extends BaseBlockParser
{
    private EventSignupBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new EventSignupBlockRenderer();
    }
    public function getType(): string
    {
        return 'event-signup';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'showName' => [new BooleanRule()],
            'showEmail' => [new BooleanRule()],
            'showPhone' => [new BooleanRule()],
            'showCompany' => [new BooleanRule()],
            'showDietaryReqs' => [new BooleanRule()],
            'showAccessibilityReqs' => [new BooleanRule()],
            'submitButtonText' => [new RequiredRule(), new MaxLengthRule(50)],
            'successMessage' => [new MaxLengthRule(500)],
            'recipientEmail' => [new EmailRule()],
            'requireName' => [new BooleanRule()],
            'requireEmail' => [new BooleanRule()],
            'requirePhone' => [new BooleanRule()],
            'requireCompany' => [new BooleanRule()],
            'autoConfirmation' => [new BooleanRule()],
            'trackCapacity' => [new BooleanRule()],
            'maxSignups' => [new MaxLengthRule(10)]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = EventSignupBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = EventSignupBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}