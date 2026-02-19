<?php

namespace App\Enums\Boost;

enum SuggestionType: string
{
    case HighPotentialLowVisibility = 'high_potential_low_visibility';
    case StrongDeal = 'strong_deal';
    case SlowMoverInventoryRisk = 'slow_mover_inventory_risk';
    case TopRated = 'top_rated';
    case BoostEndingSoon = 'boost_ending_soon';
}