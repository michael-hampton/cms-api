<?php

namespace App\Services\ValueObjects;

class Email
{
    private string $value;

    public function __construct(string $email)
    {
        $this->value = strtolower(trim($email));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}