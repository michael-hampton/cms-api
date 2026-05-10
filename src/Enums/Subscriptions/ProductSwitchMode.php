<?php

namespace App\Enums\Subscriptions;

enum ProductSwitchMode: string
{
    /** Carry over pro-rated monetary credit from the old subscription. */
    case TRANSFER_ENTITLEMENT = 'transfer_entitlement';

    /** Start fresh with a full new subscription charge; no credit applied. */
    case START_FRESH = 'start_fresh';
}