<?php

namespace App\Enums\Subscriptions;

enum SubscriptionIssueFulfilmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case SUPERSEDED = 'superseded';

    /**
     * Terminal state for a BACK_ISSUE fulfilment once
     * BackIssueReplacementCopyDispatchService has successfully sent it to the
     * vendor. Kept distinct from DELIVERED, which is written by the
     * subscriber-facing delivery-confirmation flow and carries different
     * semantics (delivered_at vs fulfilled_at).
     */
    case FULFILLED = 'fulfilled';
}
