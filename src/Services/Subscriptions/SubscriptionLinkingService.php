<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionLinked;
use App\Exceptions\Subscriptions\SubscriptionAlreadyLinkedException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Database\Database;
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
        private readonly Database $database,
    ) {
    }

    /**
     * Link a print subscription to a member account.
     *
     * The account number is prefixed with the Brand ID before lookup, matching
     * expected format: [Brand ID][Account Number].
     *
     * @throws SubscriptionNotFoundException       No match for account number + postcode
     * @throws SubscriptionAlreadyLinkedException  Subscription is claimed by a different member
     */
    public function linkToMember(
        int    $memberId,
        string $accountNumber,
        string $postcode,
        int    $siteId,
    ): Subscription {
        $contextualAccountNumber = $this->buildAccountNumber($siteId, trim($accountNumber));
        $normalisedPostcode      = $this->normalisePostcode($postcode);

        $subscriptionWithAddress = $this->printSubscriptionRepository
            ->findByAccountNumberAndPostcode($contextualAccountNumber, $normalisedPostcode, $siteId);

        if (!$subscriptionWithAddress) {
            throw new SubscriptionNotFoundException(
                'No subscription found matching those details.'
            );
        }

        $subscription = $subscriptionWithAddress->subscription;

        // Already linked to a different member — surface the owning email.
        if ($subscription->is_linked && $subscription->member_id !== $memberId) {
            throw new SubscriptionAlreadyLinkedException(
                $subscription->member?->email ?? '',
            );
        }

        // Idempotent: already linked to this member — return as-is.
        // Event is intentionally not re-emitted; listeners are not idempotent
        // (access grants and confirmation emails must not fire twice).
        if ($subscription->is_linked && $subscription->member_id === $memberId) {
            return $subscription;
        }

        $linked = $this->database->transaction(function () use ($subscription, $memberId): Subscription {
            return $this->printSubscriptionRepository->linkToMember(
                $subscription->id,
                $memberId,
            );
        });

        // Event is dispatched outside the transaction so that listener failures
        // do not roll back the committed link write.
        event(new SubscriptionLinked(
            subscription: $linked,
            memberId:     $memberId,
            siteId:       $siteId,
        ));

        return $linked;
    }

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

    // ── Private ───────────────────────────────────────────────────────

    /**
     * @param int $siteId
     * @param string $accountNumber
     * @return string
     */
    private function buildAccountNumber(int $siteId, string $accountNumber): string
    {
        return $siteId . $accountNumber;
    }

    /**
     * Uppercase and collapse whitespace: "sw1a 1aa" → "SW1A1AA"
     * Must match the normalisation applied when storing postcodes.
     */
    private function normalisePostcode(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode));
    }
}