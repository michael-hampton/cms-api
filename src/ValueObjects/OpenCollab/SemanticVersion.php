<?php

namespace App\ValueObjects\OpenCollab;

use InvalidArgumentException;

final class SemanticVersion
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        $parts = explode('.', $value);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid semantic version.');
        }

        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                throw new InvalidArgumentException('Invalid semantic version.');
            }
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
