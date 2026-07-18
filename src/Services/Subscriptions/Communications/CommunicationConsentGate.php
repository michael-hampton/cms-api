<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Enums\Member\CampaignPurpose;
use App\Enums\Subscriptions\CommunicationSuppressionReason;
use App\Models\Member;
use App\Models\SubscriptionCommunication;
use App\Services\Members\Consents\ConsentQueryService;

/**
 * Single choke point for whether a subscription communication may be
 * created at all, and (separately) whether a specific resolved channel
 * may be used for it. Checked by SubscriptionCommunicationSender before
 * every send — every communication type goes through this, not just
 * marketing ones.
 *
 * Deliberately reuses the existing marketing consent system
 * (ConsentQueryService + the 'marketing_email' consent type, same as
 * CampaignConsentChecker) rather than a parallel mechanism — the
 * member_consents table remains the single source of truth for marketing
 * permission state.
 *
 * Checks, in order:
 *   1. No member at all                         → blocks (NO_MEMBER)
 *   2. Member is deceased                        → blocks (MEMBER_DECEASED), any purpose
 *   3. Purpose is MARKETING and member is a minor → blocks (MINOR_MARKETING_EXCLUDED)
 *   4. Purpose requires marketing consent and the
 *      member's current marketing_email consent
 *      is not active                             → blocks (MARKETING_CONSENT_NOT_GIVEN)
 *   Service/transactional communications (the vast majority — payment
 *   notices, renewal reminders, first issue, etc.) are unaffected by (3)
 *   and (4): they are recognised as distinct from marketing per
 *   CampaignPurpose::requiresConsent().
 *
 * Channel-level check (checkChannel):
 *   - 'letter' channel and member has do_not_mail set → blocks (DO_NOT_MAIL)
 *     Deliberately scoped to the letter channel only — do-not-mail is a
 *     postal suppression, not a blanket communication ban.
 */
class CommunicationConsentGate
{
    public function __construct(
        private readonly ConsentQueryService $consentQuery,
    ) {
    }

    /**
     * Whole-communication check, before any channel is chosen.
     */
    public function evaluate(SubscriptionCommunication $communication, ?Member $member): ?CommunicationSuppressionReason
    {
        if ($member === null) {
            return CommunicationSuppressionReason::NO_MEMBER;
        }

        if ($member->is_deceased) {
            return CommunicationSuppressionReason::MEMBER_DECEASED;
        }

        $purpose = $communication->purpose instanceof CampaignPurpose
            ? $communication->purpose
            : (CampaignPurpose::tryFrom((string) $communication->purpose) ?? CampaignPurpose::TRANSACTIONAL);

        if ($purpose === CampaignPurpose::MARKETING && $member->is_minor) {
            return CommunicationSuppressionReason::MINOR_MARKETING_EXCLUDED;
        }

        if ($purpose->requiresConsent()) {
            if (!$this->hasMarketingConsent($member)) {
                return CommunicationSuppressionReason::MARKETING_CONSENT_NOT_GIVEN;
            }
        }

        return null;
    }

    /**
     * Channel-specific check, run after CommunicationChannelResolver has
     * picked a channel.
     */
    public function evaluateChannel(Member $member, string $channel): ?CommunicationSuppressionReason
    {
        if ($channel === 'letter' && $member->do_not_mail) {
            return CommunicationSuppressionReason::DO_NOT_MAIL;
        }

        return null;
    }

    private function hasMarketingConsent(Member $member): bool
    {
        try {
            return $this->consentQuery->hasConsent($member, 'marketing_email');
        } catch (\Throwable) {
            // Consent type missing or query failure — block rather than
            // accidentally send non-consented marketing, same conservative
            // default as CampaignConsentChecker::canSend.
            return false;
        }
    }
}
