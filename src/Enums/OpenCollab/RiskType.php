<?php

namespace App\Enums\OpenCollab;

enum RiskType: string
{
    case Copyright = 'copyright';
    case AiGenerated = 'ai_generated';
    case MusicRights = 'music_rights';
    case BrandSafety = 'brand_safety';
    case AffiliateLinkAbuse = 'affiliate_link_abuse';
    case SponsoredContent = 'sponsored_content';
    case UnclearOwnership = 'unclear_ownership';
    case MissingProvenance = 'missing_provenance';
    case Other = 'other';
}