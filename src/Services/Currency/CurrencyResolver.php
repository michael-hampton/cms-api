<?php

namespace App\Services\Currency;

use App\Framework\Support\SiteContext;

class CurrencyResolver
{
    public function resolve(?int $siteId = null): string
    {
        $siteId = $siteId ?? SiteContext::getId();

        // Get from site configuration
        $currency = config("sites.{$siteId}.currency");

        if ($currency) {
            return strtolower($currency);
        }

        // Fall back to default
        return strtolower(config('checkout.default_currency', 'usd'));
    }

    public function resolveUpperCase(?int $siteId = null): string
    {
        return strtoupper($this->resolve($siteId));
    }
}