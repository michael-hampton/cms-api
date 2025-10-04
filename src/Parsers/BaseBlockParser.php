<?php

namespace App\Parsers;

abstract class BaseBlockParser implements BlockParserInterface
{
    public function supports(string $type): bool
    {
        return $this->getType() === $type;
    }
}