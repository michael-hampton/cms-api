<?php

namespace App\Parsers;

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\AgentProfileBlockDto;
use App\Parsers\Renderers\AgentProfileBlockRenderer;

class AgentProfileBlockParser extends BaseBlockParser
{
    private AgentProfileBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new AgentProfileBlockRenderer();
    }

    public function getType(): string
    {
        return 'agent-profile';
    }

    public function getValidationRules(): array
    {
        return [
            'name' => [new RequiredRule(), new MaxLengthRule(255)],
            'title' => [new MaxLengthRule(255)],
            'bio' => [new MaxLengthRule(2000)],
            'phone' => [new MaxLengthRule(50)],
            'email' => [new EmailRule()],
            'license' => [new MaxLengthRule(255)],
            'experience' => [new MaxLengthRule(255)],
            'specialties' => [new MaxLengthRule(500)],
            'languages' => [new MaxLengthRule(255)],
            'profileImageUrl' => [new MaxLengthRule(500)],
            'socialMedia' => [new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = AgentProfileBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = AgentProfileBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }


}