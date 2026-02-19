<?php

namespace App\Exceptions\Boost;

class BoostNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Boost [{$id}] not found.");
    }
}