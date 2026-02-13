<?php

namespace App\Services\Shipping;

interface FulfilmentTypeInterface
{
    public function requiresShipping(): bool;

    public function dispatchDays(): int;
}