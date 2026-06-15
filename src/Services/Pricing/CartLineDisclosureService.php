<?php

namespace App\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;
use DateTimeImmutable;

final readonly class CartLineDisclosureService
{
    public function __construct(private PriceDisclosureFormatter $formatter)
    {
    }

    /**
     * Add resolved disclosure copy to a cart item without leaking billing rules
     * into the checkout view or future SDUI schemas.
     *
     * Expected plan facts are deliberately scalar so callers may source them
     * from models, cached cart snapshots, or remote Experience configuration.
     */
    public function enrich(
        array $item,
        array $planFacts,
        string $locale,
        string $currency,
        array $copyOverrides = [],
        array $formatterSettings = [],
    ): array {
        if (empty($item['subscription_plan_id'])) {
            return $item;
        }

        $unitAmountMinor = isset($item['amount_minor'])
            ? (int)$item['amount_minor']
            : (int)round(((float)($item['price'] ?? $item['amount'] ?? 0)) * 100);

        $renewalDate = !empty($planFacts['renewal_date'])
            ? new DateTimeImmutable((string)$planFacts['renewal_date'])
            : null;

        $context = new PriceDisclosureContext(
            locale: $locale,
            currency: $currency,
            quantity: (int)($item['quantity'] ?? 1),
            unitAmountMinor: $unitAmountMinor,
            billingPeriod: (string)($planFacts['billing_period'] ?? 'monthly'),
            billingInterval: (int)($planFacts['billing_interval'] ?? 1),
            trialDays: isset($planFacts['trial_days']) ? (int)$planFacts['trial_days'] : null,
            renewalDate: $renewalDate,
            isRecurring: !((bool)($planFacts['is_one_time'] ?? false)),
            copyOverrides: $copyOverrides,
            formatterSettings: $formatterSettings,
        );

        $item['line_summary'] = $this->formatter->format($context);

        return $item;
    }
}
