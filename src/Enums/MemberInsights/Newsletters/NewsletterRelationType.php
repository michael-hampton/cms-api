<?php

namespace App\Enums\MemberInsights\Newsletters;

enum NewsletterRelationType: string
{
    case SameBrand = 'same_brand';
    case SameCategory = 'same_category';
    case ComplementaryTopic = 'complementary_topic';
    case UpsellPremium = 'upsell_premium';

    /**
     * Higher score = ranked earlier in recommendations.
     * Scores are relative — only ordering matters, not absolute values.
     */
    public function score(): int
    {
        return match ($this) {
            self::UpsellPremium => 40,
            self::SameBrand => 30,
            self::SameCategory => 20,
            self::ComplementaryTopic => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UpsellPremium => 'Premium version',
            self::SameBrand => 'Same brand',
            self::SameCategory => 'Same category',
            self::ComplementaryTopic => 'Complementary topic',
        };
    }
}