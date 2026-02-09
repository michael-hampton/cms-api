<?php

namespace App\Services\Newsletter\DTOs\BlockData;

abstract class BaseBlockData
{
    abstract public static function fromArray(array $data): self;
}