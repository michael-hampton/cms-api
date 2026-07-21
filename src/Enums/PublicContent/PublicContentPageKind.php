<?php

namespace App\Enums\PublicContent;

/**
 * First-class page kinds the public content renderer can dispatch on.
 *
 * Every resolvable route target maps to a known case, {@see self::Unknown},
 * or {@see self::Invalid}. There is no silent fallback.
 */
enum PublicContentPageKind: string
{
    case Article = 'article';
    case Homepage = 'homepage';
    case Category = 'category';
    case Review = 'review';
    case BuyingGuide = 'buying-guide';
    case Content = 'content';
    case LandingPage = 'landing-page';
    case Unknown = 'unknown';
    case Invalid = 'invalid';
}
