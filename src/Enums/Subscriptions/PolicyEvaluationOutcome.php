<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * The outcome of a ReplacementPolicyInterface::evaluate() call.
 *
 * DENIED, REQUIRES_MANAGER_APPROVAL, and BUSINESS_OVERRIDE_REQUIRED are all
 * "not currently allowed" outcomes — they differ only in *why*, which feeds
 * the blockedReason shown to the agent and future reporting. As of this
 * ticket, IssueResolutionService treats all three identically: it throws
 * unless the agent supplied a business override, in which case the
 * GoodwillPolicy is substituted regardless of which of these three the
 * original policy returned. Manager-approval routing (as opposed to a
 * straight agent override) is explicitly out of scope per the ticket.
 */
enum PolicyEvaluationOutcome: string
{
    case ALLOWED = 'allowed';
    case DENIED = 'denied';
    case REQUIRES_MANAGER_APPROVAL = 'requires_manager_approval';
    case BUSINESS_OVERRIDE_REQUIRED = 'business_override_required';

    public function isGranted(): bool
    {
        return $this === self::ALLOWED;
    }
}
