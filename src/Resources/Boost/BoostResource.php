<?php

namespace App\Resources\Boost;

use App\Models\Boost;

class BoostResource
{
    public function __construct(private readonly Boost $boost)
    {
    }

    public static function collection(iterable $boosts): array
    {
        return array_map(
            fn(Boost $b) => (new self($b))->toArray(),
            is_array($boosts) ? $boosts : $boosts->all()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->boost->id,
            'boostable_type' => $this->boost->boostable_type,
            'boostable_id' => $this->boost->boostable_id,
            'merchant_id' => $this->boost->merchant_id,
            'context' => $this->boost->context,
            'starts_at' => $this->boost->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->boost->ends_at?->format('Y-m-d H:i:s'),
            'multiplier' => $this->boost->multiplier,
            'status' => $this->boost->status,
            'price_paid' => $this->boost->price_paid,
            'currency' => $this->boost->currency,
            'payment_reference' => $this->boost->payment_reference,
            'created_at' => $this->boost->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}