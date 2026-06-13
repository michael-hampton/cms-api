<?php

namespace App\DTO\OpenCollab;

final class ImageBlockValidationResult
{
    /** @param array<string, string[]> $errors  keyed by block identifier */
    public function __construct(
        public readonly bool  $passes,
        public readonly array $errors = [],
    ) {
    }

    public static function ok(): self
    {
        return new self(passes: true);
    }

    public static function fail(array $errors): self
    {
        return new self(passes: false, errors: $errors);
    }
}