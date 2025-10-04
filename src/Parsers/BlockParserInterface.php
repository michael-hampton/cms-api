<?php

namespace App\Parsers;

interface BlockParserInterface
{
    public function getType(): string;
    public function getValidationRules(): array;
    public function parse(array $data): array;
    public function supports(string $type): bool;
    public function generateHtml(array $parsedData): string;
}