<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\ContactFormBlockDto;
use App\Parsers\Renderers\ContactFormBlockRenderer;

class ContactFormBlockParser extends BaseBlockParser
{
    private ContactFormBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new ContactFormBlockRenderer();
    }
    public function getType(): string
    {
        return 'contact-form';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'showName' => [new BooleanRule()],
            'showEmail' => [new BooleanRule()],
            'showPhone' => [new BooleanRule()],
            'showSubject' => [new BooleanRule()],
            'showMessage' => [new BooleanRule()],
            'showPropertyInterest' => [new BooleanRule()],
            'submitButtonText' => [new RequiredRule(), new MaxLengthRule(50)],
            'successMessage' => [new MaxLengthRule(500)],
            'recipientEmail' => [new EmailRule()],
            'requireName' => [new BooleanRule()],
            'requireEmail' => [new BooleanRule()],
            'requirePhone' => [new BooleanRule()],
            'requireSubject' => [new BooleanRule()],
            'requireMessage' => [new BooleanRule()],
            'override_email' => [new EmailRule()],
            'override_phone' => [new MaxLengthRule(50)],
            'override_address' => [new ArrayRule()],
            'override_social' => [new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = ContactFormBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = ContactFormBlockDto::fromArray($parsedData);

        return $this->renderer->render($dto);
    }
}