<?php

namespace App\Enums\Orders;

enum OrderLineStatus: string
{
    case PENDING_PREORDER = 'pending_preorder';
    case READY_TO_SHIP = 'ready_to_ship';
    case SHIPPED = 'shipped';
    case CANCELLED = 'cancelled';
    case PENDING = 'pending';

    public function isPending(): bool
    {
        return $this === self::PENDING_PREORDER || $this === self::PENDING;
    }

    public function canBeAllocated(): bool
    {
        return $this === self::PENDING_PREORDER;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::PENDING_PREORDER, self::READY_TO_SHIP]);
    }
}