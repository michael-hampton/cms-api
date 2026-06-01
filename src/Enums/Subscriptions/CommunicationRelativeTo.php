<?php

namespace App\Enums\Subscriptions;

enum CommunicationRelativeTo: string
{
    case RENEWAL_DATE          = 'renewal_date';
    case SUBSCRIPTION_START_DATE = 'subscription_start_date';
    case SUBSCRIPTION_END_DATE = 'subscription_end_date';
    case CCC_EXPIRY_DATE       = 'ccc_expiry_date';
    case SEGMENT_ASSIGNED_AT   = 'segment_assigned_at';
}