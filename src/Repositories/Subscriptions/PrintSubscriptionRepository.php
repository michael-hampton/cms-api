<?php

namespace App\Repositories\Subscriptions;

use App\DTO\Subscriptions\SubscriptionWithAddress;
use App\Models\Subscription;
use App\Repositories\Repository;

/**
 * Handles print-subscription lookup and linking.
 *
 * Lookup strategy:
 *   subscriptions (account_number) → members (id) → addresses (postcode)
 *
 * "Unlinked" is defined by is_linked = false on the Subscription record.
 * member_id may already be populated (e.g. from import) — is_linked is
 * the authoritative flag.
 */
class PrintSubscriptionRepository extends Repository
{
    /**
     * True only when the member has an active subscription that has been
     * explicitly linked (is_linked = true).
     *
     * An active-but-unlinked subscription must NOT skip Step 3 — that is
     * exactly the case Step 3 exists to handle.
     */
    public function hasLinkedActiveSubscription(int $memberId, int $siteId): bool
    {
        $now = now();

        return Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('is_linked', true)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->exists();
    }

    /**
     * Find a print subscription whose account number matches and whose
     * owner's default shipping address matches the supplied postcode.
     *
     * Returns both linked and unlinked records — the service layer decides
     * what to do with each case.
     */
    public function findByAccountNumberAndPostcode(
        string $accountNumber,
        string $normalisedPostcode,
        int    $siteId
    ): ?SubscriptionWithAddress
    {
        $sql = <<<SQL
        SELECT s.*, a.postcode, a.type, a.is_default
        FROM subscriptions s
        INNER JOIN members m
            ON m.id = s.member_id
        INNER JOIN addresses a
            ON a.member_id = m.id
            AND a.type IN ('shipping', 'both')
            AND a.is_default = 1
        WHERE s.account_number  = ?
          AND UPPER(REPLACE(a.postcode, ' ', '')) = ?
        LIMIT 1
    SQL;

        $stmt = $this->database->query($sql, [
            $accountNumber,
            $normalisedPostcode,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        return new SubscriptionWithAddress(
            Subscription::hydrateStatic($row),
            $row['postcode'],
            $row['type'],
            (bool)$row['is_default']
        );
    }

    /**
     * Mark a subscription as linked to the given member.
     * Single write — no transaction wrapper required.
     */
    public function linkToMember(int $subscriptionId, int $memberId): Subscription
    {
        $this->update($subscriptionId, [
            'member_id' => $memberId,
            'is_linked' => true,
        ]);

        return $this->find($subscriptionId);
    }

    protected function getModelClass(): string
    {
        return Subscription::class;
    }
}