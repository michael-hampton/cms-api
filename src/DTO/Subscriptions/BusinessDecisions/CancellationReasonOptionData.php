<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

/**
 * One reason's resolved options plus any offers available to present in
 * the save journey, ready for the cancellation-options API response.
 *
 * @param \App\DTO\Subscriptions\SubscriptionOfferData[] $availableOffers
 */
final class CancellationReasonOptionData
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly ResolvedCancellationOptions $options,
        public readonly array $availableOffers = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'options' => $this->options->toArray(),
            'available_offers' => array_map(
                static fn ($offer) => $offer->toArray(),
                $this->availableOffers,
            ),
        ];
    }
}
