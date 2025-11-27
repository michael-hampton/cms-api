<?php

namespace App\Parsers;

use App\Framework\Validation\Validator;

abstract class BaseBlockParser implements BlockParserInterface
{
    abstract public function getType(): string;

    abstract public function getValidationRules(): array;

    abstract public function parse(array $data): array;

    abstract public function generateHtml(array $parsedData): string;

    public function validate(array $data): array
    {
        $validator = new Validator();
        $rules = array_merge(
            $this->getValidationRules(),
            $this->getCommonValidationRules()
        );

        return $validator->validate($data, $rules);
    }

    protected function getCommonValidationRules(): array
    {
        return [
            'context' => [
                new \App\Framework\Validation\Rules\InRule(['default', 'sidebar'])
            ]
        ];
    }

    protected function getContext(array $data): string
    {
        return $data['context'] ?? 'default';
    }

    protected function isSidebarContext(array $data): bool
    {
        return $this->getContext($data) === 'sidebar';
    }

    public function supports(string $type): bool
    {
        return $this->getType() === $type;
    }
}