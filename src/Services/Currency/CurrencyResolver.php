<?php

namespace App\Services\Currency;

use App\Framework\Support\SiteContext;

class CurrencyResolver
{
    public function resolve(?int $siteId = null): string
    {
        $siteId = $siteId ?? SiteContext::getId();

        $currency = config("sites.{$siteId}.currency");

        if ($currency) {
            return strtolower($currency);
        }

        return strtolower(config('checkout.default_currency', 'usd'));
    }

    public function resolveUpperCase(?int $siteId = null): string
    {
        return strtoupper($this->resolve($siteId));
    }

    /**
     * Resolve currency from a plan's own currency field, falling back to
     * the site-level config. Use this for subscription plan contexts where
     * the plan may carry its own currency (e.g. a GBP plan on a multi-currency site).
     */
    public function resolveForPlan(?string $planCurrency, ?int $siteId = null): string
    {
        if ($planCurrency && strlen(trim($planCurrency)) === 3) {
            return strtolower(trim($planCurrency));
        }

        return $this->resolve($siteId);
    }

    public function resolveForPlanUpperCase(?string $planCurrency, ?int $siteId = null): string
    {
        return strtoupper($this->resolveForPlan($planCurrency, $siteId));
    }

    /**
     * Returns the symbol for a given ISO 4217 currency code.
     * Extend as needed for currencies your platform supports.
     */
    public function symbol(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'GBP' => '£',
            'EUR' => '€',
            'USD' => '$',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => strtoupper($currencyCode) . ' ',
        };
    }
}