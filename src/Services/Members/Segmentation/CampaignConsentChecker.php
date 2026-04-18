<?php

namespace App\Services\Members\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Enums\Member\CampaignPurpose;
use App\Models\Member;
use App\Services\Members\Consents\ConsentQueryService;

/**
 * Determines whether a campaign may be sent to a member on a given channel.
 *
 * Maps the campaign's purpose + channel combination to the existing
 * consent type codes used by ConsentQueryService. This avoids duplicating
 * the consent record structure — the underlying MemberConsent table and
 * its audit trail remain the single source of truth.
 *
 * Consent type code mapping:
 *   marketing       + email        → marketing_email
 *   marketing       + notification → marketing_email
 *   marketing       + push         → marketing_email  (until push-specific type is added)
 *   product_updates + (any)        → communication_preferences
 *   transactional   + (any)        → bypass, no check
 *
 * Adding a new channel: add a case to the marketing branch of consentTypeCode().
 * Adding a new purpose: add a branch to consentTypeCode() AND a case to requiresConsent().
 */
class CampaignConsentChecker
{
    public function __construct(
        private readonly ConsentQueryService $consentQuery,
    )
    {
    }

    /**
     * Returns true when the campaign MAY be sent on the given channel.
     * Never throws — missing or unknown consent is treated as denied.
     */
    public function canSend(Member $member, CampaignPurpose $purpose, CampaignChannel $channel): bool
    {
        if (!$purpose->requiresConsent()) {
            return true;
        }

        $consentCode = $this->consentTypeCode($purpose, $channel);

        if ($consentCode === null) {
            // No mapping defined for this purpose+channel combination.
            // Conservative default: block rather than send without consent.
            return false;
        }

        try {
            return $this->consentQuery->hasConsent($member, $consentCode);
        } catch (\Throwable) {
            // Consent type not found in DB, or query failure.
            // Block rather than accidentally send non-consented marketing.
            return false;
        }
    }

    /**
     * Returns the consent_type code that governs this purpose + channel pair,
     * or null if no mapping exists (which causes canSend to block).
     *
     * Kept as explicit flat cases rather than a nested match so that:
     *   (a) adding a new CampaignChannel enum case forces a deliberate
     *       decision here rather than silently throwing UnhandledMatchError
     *   (b) the mapping is readable without tracing nested branches
     */
    private function consentTypeCode(CampaignPurpose $purpose, CampaignChannel $channel): ?string
    {
        return match (true) {
            $purpose === CampaignPurpose::PRODUCT_UPDATES => 'communication_preferences',

            $purpose === CampaignPurpose::MARKETING && $channel === CampaignChannel::EMAIL => 'marketing_email',
            $purpose === CampaignPurpose::MARKETING && $channel === CampaignChannel::NOTIFICATION => 'marketing_email',
            $purpose === CampaignPurpose::MARKETING && $channel === CampaignChannel::PUSH => 'marketing_email',

            // TRANSACTIONAL is handled by requiresConsent() before this method
            // is ever called, but we need a default to keep the match exhaustive.
            default => null,
        };
    }
}