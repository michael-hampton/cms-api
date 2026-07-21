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

    /**
     * The owning subscription has an unresolved payment problem (a failed
     * payment or an admin/system suspension). Set by
     * FulfilmentSuspensionService. Undispatched, unsuperseded only.
     *
     * Distinct from PAUSED: suspension is enforcement-driven and is lifted
     * by FulfilmentSuspensionService::release() when the underlying problem
     * clears — the same rows go back to SCHEDULED. It is not replaced with
     * new schedule rows the way a PAUSED fulfilment is on resume.
     */
    case SUSPENDED = 'suspended';

    /**
     * The owning subscription has a subscription-level pause in effect
     * (SubscriptionPauseService). Set by SubscriptionFulfilmentPauseService::pause().
     * On resume, PAUSED rows are superseded and replaced with fresh rows
     * from the next available plan issue — see
     * SubscriptionFulfilmentPauseService::resume().
     */
    case PAUSED = 'paused';

    /**
     * Terminal state written when the owning subscription is cancelled.
     * Set by FulfilmentCancellationService. A cancelled row is never
     * reactivated — a resubscribe creates a new subscription and new
     * fulfilment rows.
     */
    case CANCELLED = 'cancelled';
}
