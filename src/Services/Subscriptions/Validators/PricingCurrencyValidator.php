<?php

namespace App\Services\Subscriptions\Validators;

/**
 * Validates that a currency code is on the supported billing whitelist
 * before it is passed to Stripe or persisted.
 *
 * This is a pure domain rule with no infrastructure dependencies.
 * Both AddPlanPriceAction and ReplacePlanPriceAction must pass input
 * through this validator before calling the Stripe gateway.
 */
class PricingCurrencyValidator
{
    /**
     * ISO 4217 codes supported by our billing integration.
     * Must match the currencies enabled on the Stripe account.
     */
    private const SUPPORTED_CURRENCIES = ['usd', 'gbp', 'eur', 'aud', 'cad'];

    /**
     * @throws \InvalidArgumentException If the currency is not supported.
     */
    public function validate(string $currency): string
    {
        $normalised = strtolower(trim($currency));

        if (!in_array($normalised, self::SUPPORTED_CURRENCIES, true)) {
            throw new \InvalidArgumentException(
                "Currency \"{$currency}\" is not supported. Supported currencies: "
                . implode(', ', self::SUPPORTED_CURRENCIES) . '.'
            );
        }

        return $normalised;
    }
}