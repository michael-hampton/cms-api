<?php

namespace App\DTO\Checkout;

use DateTimeImmutable;

final class EstimatedDelivery
{
    public function __construct(
        public readonly bool               $requiresShipping,
        public readonly ?DateTimeImmutable $dispatchDate,
        public readonly ?DateTimeImmutable $from,
        public readonly ?DateTimeImmutable $to,
    )
    {
    }

    public static function digital(): self
    {
        return new self(
            requiresShipping: false,
            dispatchDate: null,
            from: null,
            to: null
        );
    }

    public static function physical(
        DateTimeImmutable $dispatchDate,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): self
    {
        return new self(
            requiresShipping: true,
            dispatchDate: $dispatchDate,
            from: $from,
            to: $to
        );
    }

    public function formattedRange(): string
    {
        if (!$this->requiresShipping) {
            return 'Available immediately after payment';
        }

        if ($this->from->format('Y-m-d') === $this->to->format('Y-m-d')) {
            return $this->from->format('j M Y');
        }

        return $this->from->format('j M') . ' – ' . $this->to->format('j M Y');
    }

    public function formattedDispatch(): string
    {
        if (!$this->requiresShipping || !$this->dispatchDate) {
            return 'N/A';
        }

        return $this->dispatchDate->format('j M Y');
    }
}