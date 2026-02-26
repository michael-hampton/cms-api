<?php

namespace App\DTO\Checkout;

final class EligibilityResult
{
    public function __construct(
        public readonly array $valid,
        public readonly array $removed
    )
    {
    }

    public function hasRemovedItems(): bool
    {
        return !empty($this->removed);
    }

    public function isEmpty(): bool
    {
        return empty($this->valid);
    }
}