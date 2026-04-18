<?php

namespace App\Enums\Member;

enum CampaignPurpose: string
{
    case MARKETING = 'marketing';
    case PRODUCT_UPDATES = 'product_updates';
    case TRANSACTIONAL = 'transactional';

    /**
     * Transactional campaigns bypass consent checks.
     * All other purposes require explicit member consent.
     */
    public function requiresConsent(): bool
    {
        return $this !== self::TRANSACTIONAL;
    }
}
