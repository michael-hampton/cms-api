<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationCategory;

/**
 * Pure mapping — category to responsible team. No persistence, no I/O.
 */
class EscalationRoutingService
{
    private const ROUTING = [
        EscalationCategory::AiGenerated->value => 'editorial',
        EscalationCategory::MusicRights->value => 'legal',
        EscalationCategory::Copyright->value => 'legal',
        EscalationCategory::BrandSafety->value => 'brand_safety',
        EscalationCategory::AffiliateAbuse->value => 'commercial_compliance',
        EscalationCategory::SponsoredContent->value => 'commercial_compliance',
        EscalationCategory::Legal->value => 'legal',
        EscalationCategory::Other->value => 'editorial',
    ];

    public function teamFor(EscalationCategory $category): string
    {
        return self::ROUTING[$category->value] ?? 'editorial';
    }
}