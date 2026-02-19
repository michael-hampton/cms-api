<?php

namespace App\Models\Concerns;

trait IsBoostable
{
    public function getBoostableId(): int
    {
        return $this->id;
    }

    public function getBoostableType(): string
    {
        return static::BOOSTABLE_TYPE;
    }

    public function isInStock(): bool
    {
        return (bool)($this->in_stock ?? ($this->stock_quantity > 0));
    }

    abstract public function scopeBoostable($query);
}