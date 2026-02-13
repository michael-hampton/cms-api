<?php

namespace App\DTO\Checkout;

final class DeliveryMethodConfig
{
    public function __construct(
        public readonly string $cutoffTime,
        public readonly int    $transitMinDays,
        public readonly int    $transitMaxDays,
    )
    {
    }

    public static function default(): self
    {
        return new self(
            cutoffTime: config('shipping.default_cutoff_time', '14:00'),
            transitMinDays: config('shipping.default_transit_min_days', 2),
            transitMaxDays: config('shipping.default_transit_max_days', 5)
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cutoffTime: $data['cutoff_time'] ?? '14:00',
            transitMinDays: $data['transit_min_days'] ?? 2,
            transitMaxDays: $data['transit_max_days'] ?? 5
        );
    }
}