<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionLinked;
use App\Exceptions\Subscriptions\SubscriptionAlreadyLinkedException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Models\Subscription;
use App\Repositories\Subscriptions\PrintSubscriptionRepository;

/**
 * Owns the print-subscription linking handshake.
 *
 * Single responsibility: validate the account number + postcode pair,
 * enforce linking guards, perform the write, and emit the domain event.
 * All side effects (digital access grants, confirmation email) are handled
 * by listeners on SubscriptionLinked.
 */
class SubscriptionLinkingService
{
    public function __construct(
        private readonly PrintSubscriptionRepository $printSubscriptionRepository,
    )
    {
    }

    /**
     * Link a print subscription to a member account.
     *
     * @throws SubscriptionNotFoundException       No match for account number + postcode
     * @throws SubscriptionAlreadyLinkedException  Subscription is claimed by a different member
     */
    public function linkToMember(
        int    $memberId,
        string $accountNumber,
        string $postcode,
        int    $siteId,
    ): Subscription
    {
        $accountNumber = trim($accountNumber);
        $normalisedPostcode = $this->normalisePostcode($postcode);

        $subscription = $this->printSubscriptionRepository
            ->findByAccountNumberAndPostcode($accountNumber, $normalisedPostcode, $siteId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException(
                'No subscription found matching those details.'
            );
        }

        // Already linked to a different member
        if ($subscription->subscription->is_linked && $subscription->subscription->member_id !== $memberId) {
            throw new SubscriptionAlreadyLinkedException(
                $subscription->subscription->member?->email ?? '',
            );
        }

        // Idempotent: already linked to this member — return as-is
        if ($subscription->subscription->is_linked && $subscription->subscription->member_id === $memberId) {
            return $subscription->subscription;
        }

        $linked = $this->printSubscriptionRepository->linkToMember(
            $subscription->subscription->id,
            $memberId,
        );

        event(new SubscriptionLinked(
            subscription: $linked,
            memberId: $memberId,
            siteId: $siteId,
        ));

        return $linked;
    }

    /**
     * Uppercase and collapse whitespace: "sw1a 1aa" → "SW1A1AA"
     * Must match the normalisation applied when storing postcodes.
     */
    private function normalisePostcode(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode));
    }

    // ── Private ───────────────────────────────────────────────────────

    /**
     * True only when the member has an active subscription that is also linked
     * (is_linked = true). An active-but-unlinked subscription returns false —
     * the member still needs to go through Step 3.
     */
    public function memberHasLinkedSubscription(int $memberId, int $siteId): bool
    {
        return $this->printSubscriptionRepository
            ->hasLinkedActiveSubscription($memberId, $siteId);
    }
}