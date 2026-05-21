<?php

namespace App\DTO\Stripe;

final readonly class BillingAddressData
{
    public function __construct(
        public ?string $line1,
        public ?string $line2,
        public ?string $city,
        public ?string $state,
        public ?string $postcode,
        public ?string $country,
    ) {}

    /**
     * Return a Stripe-compatible address array.
     * Null fields are omitted so Stripe does not receive empty strings.
     */
    public function toStripe(): array
    {
        return array_filter([
            'line1'       => $this->line1,
            'line2'       => $this->line2,
            'city'        => $this->city,
            'state'       => $this->state,
            'postal_code' => $this->postcode,
            'country'     => $this->country,
        ], fn (?string $v) => $v !== null && $v !== '');
    }

    /**
     * True when the address carries enough data to be useful.
     * At minimum a country must be present.
     */
    public function isUsable(): bool
    {
        return $this->country !== null && $this->country !== '';
    }

    /**
     * Whether this address differs from a Stripe customer address array
     * on any of the tracked fields.
     *
     * The Stripe customer address uses 'postal_code', not 'postcode'.
     */
    public function differsWith(array $stripeAddress): bool
    {
        $compareFields = [
            'line1'       => $this->line1,
            'line2'       => $this->line2,
            'city'        => $this->city,
            'state'       => $this->state,
            'postal_code' => $this->postcode,
            'country'     => $this->country,
        ];

        foreach ($compareFields as $key => $localValue) {
            $remoteValue = $stripeAddress[$key] ?? null;

            // Treat empty string and null as equivalent to avoid spurious syncs
            $local  = $localValue  === '' ? null : $localValue;
            $remote = $remoteValue === '' ? null : $remoteValue;

            if ($local !== $remote) {
                return true;
            }
        }

        return false;
    }
}