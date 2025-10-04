<?php

namespace App\Parsers;

class BlockRegistry
{
    private $parsers = [];

    public function register(BlockParserInterface $parser): void
    {
        $this->parsers[$parser->getType()] = $parser;
    }

    public function getParser(string $type): ?BlockParserInterface
    {
        return $this->parsers[$type] ?? null;
    }

    public function hasParser(string $type): bool
    {
        return isset($this->parsers[$type]);
    }

    public function getAvailableTypes(): array
    {
        return array_keys($this->parsers);
    }

    public function getAllParsers(): array
    {
        return $this->parsers;
    }
}