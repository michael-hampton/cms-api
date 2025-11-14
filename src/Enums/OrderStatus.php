<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case ON_HOLD = 'on_hold';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::PENDING->value => [
                self::PROCESSING,
                self::CANCELLED,
                self::ON_HOLD
            ],
            self::PROCESSING->value => [
                self::SHIPPED,
                self::COMPLETED,
                self::CANCELLED,
                self::ON_HOLD
            ],
            self::SHIPPED->value => [
                self::DELIVERED,
                self::COMPLETED,
                self::REFUNDED
            ],
            self::DELIVERED->value => [
                self::COMPLETED,
                self::REFUNDED
            ],
            self::COMPLETED->value => [
                self::REFUNDED
            ],
            self::CANCELLED->value => [],
            self::REFUNDED->value => [],
            self::ON_HOLD->value => [
                self::PENDING,
                self::PROCESSING,
                self::CANCELLED
            ]
        ];

        return in_array($newStatus, $transitions[$this->value] ?? []);
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::ON_HOLD => 'On Hold',
        };
    }
}
