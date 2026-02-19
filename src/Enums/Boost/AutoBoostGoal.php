<?php

namespace App\Enums\Boost;

enum AutoBoostGoal: string
{
    case MaximiseRevenue = 'maximise_revenue';
    case PromoteDeals = 'promote_deals';
    case ClearInventory = 'clear_inventory';
}