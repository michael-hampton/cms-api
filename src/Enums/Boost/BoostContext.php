<?php

namespace App\Enums\Boost;

enum BoostContext: string
{
    case Listing = 'listing';
    case Deals = 'deals';
    case Recommendations = 'recommendations';
}