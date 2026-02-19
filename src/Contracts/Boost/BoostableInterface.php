<?php

namespace App\Contracts\Boost;

interface BoostableInterface
{
    public function getBoostableId(): int;

    public function getBoostableType(): string;

    public function isEligibleForBoost(): bool;

    public function isInStock(): bool;
}