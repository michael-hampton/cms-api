<?php

namespace App\Services\Newsletter\DTOs;

class RenderedBlock
{
    private function __construct(
        public readonly string $html,
        public readonly bool   $wasRendered
    )
    {
    }

    public static function rendered(string $html): self
    {
        return new self($html, true);
    }

    public static function skipped(): self
    {
        return new self('', false);
    }
}