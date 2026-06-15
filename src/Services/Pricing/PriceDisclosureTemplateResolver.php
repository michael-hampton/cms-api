<?php

namespace App\Services\Pricing;

final class PriceDisclosureTemplateResolver
{
    private const DEFAULTS = [
        'en_GB' => [
            'trial_with_start_charge' => ':initial_price for :initial_charge_period_label, including a :trial_period_label trial. Then :renewal_price :renewal_period_label from :renewal_date.',
            'trial_without_start_charge' => ':trial_period_label free trial. Then :renewal_price :renewal_period_label from :renewal_date.',
            'subscription_without_trial' => ':item_price :renewal_period_label. Renews on :renewal_date at :renewal_price :renewal_period_label.',
            'renewal_without_date' => 'Renews at :renewal_price :renewal_period_label.',
            'one_time' => ':item_price',
        ],
    ];

    public function resolve(
        string $key,
        string $locale,
        array $experienceLanguageLines,
        array $storeLanguageLines,
    ): string {
        return $experienceLanguageLines[$locale][$key]
            ?? $storeLanguageLines[$locale][$key]
            ?? self::DEFAULTS[$locale][$key]
            ?? self::DEFAULTS['en_GB'][$key]
            ?? self::DEFAULTS['en_GB']['one_time'];
    }

    public function builtIn(string $key, string $locale): string
    {
        return self::DEFAULTS[$locale][$key]
            ?? self::DEFAULTS['en_GB'][$key]
            ?? self::DEFAULTS['en_GB']['one_time'];
    }
}
