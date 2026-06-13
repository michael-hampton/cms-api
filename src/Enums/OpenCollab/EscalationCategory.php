<?php

namespace App\Enums\OpenCollab;

enum EscalationCategory: string
{
    case AiGenerated = 'ai_generated';
    case MusicRights = 'music_rights';
    case Copyright = 'copyright';
    case BrandSafety = 'brand_safety';
    case AffiliateAbuse = 'affiliate_abuse';
    case SponsoredContent = 'sponsored_content';
    case Legal = 'legal';
    case Other = 'other';
}