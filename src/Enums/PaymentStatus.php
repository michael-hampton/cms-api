<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(PaymentStatus $targetStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($targetStatus, [
                self::PROCESSING,
                self::COMPLETED,
                self::FAILED,
                self::CANCELLED
            ]),
            self::PROCESSING => in_array($targetStatus, [
                self::COMPLETED,
                self::FAILED,
                self::CANCELLED
            ]),
            self::COMPLETED => $targetStatus === self::REFUNDED,
            self::FAILED => in_array($targetStatus, [
                self::PENDING,
                self::PROCESSING
            ]),
            self::CANCELLED => in_array($targetStatus, [
                self::PENDING,
                self::PROCESSING
            ]),
            self::REFUNDED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }
}
