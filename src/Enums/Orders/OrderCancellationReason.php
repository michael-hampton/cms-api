<?php

namespace App\Enums\Orders;

/**
 * Reasons a member may give when cancelling an order.
 */
enum OrderCancellationReason: string
{
    case WrongItem = 'wrong_item';
    case ChangedMind = 'changed_mind';
    case DeliveryDelay = 'delivery_delay';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WrongItem => 'Ordered the wrong item',
            self::ChangedMind => 'Changed my mind',
            self::DeliveryDelay => 'Delivery taking too long',
            self::Other => 'Other',
        };
    }
}