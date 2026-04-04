<?php

namespace App\Services\Shopping;

use App\Repositories\Shopping\CartRepository;

/**
 * Migrates cart items from a session to a member account.
 *
 * Problem: When a guest adds items to cart they are stored with session_id only.
 * After the guest authenticates (OTP, registration, or anonymous creation) the
 * CartService switches to member-id lookups and can no longer see those items.
 *
 * This service is called immediately after any login / member-creation event so
 * that all session-keyed items are stamped with the new member id before any
 * downstream service tries to read the cart.
 *
 * Rules:
 * - Only migrates items that belong to the session but have no user_id yet.
 * - Never duplicates: if the member already owns the same subscription_plan_id
 *   or product_id+variant_id, the session copy is discarded.
 * - Safe to call multiple times; subsequent calls are no-ops.
 */
class CartMigrationService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
    )
    {
    }

    /**
     * Claim all un-owned session items for the given member.
     *
     * @param int $memberId
     * @param string $sessionId
     * @return int    Number of items migrated.
     */
    public function migrateSessionCartToMember(int $memberId, string $sessionId): int
    {
        $sessionItems = $this->cartRepository->findBySessionOrUser(null, $sessionId);
        $memberItems = $this->cartRepository->findBySessionOrUser($memberId, $sessionId);

        // Index existing member items to detect conflicts.
        $memberSubscriptionPlanIds = [];
        $memberProductKeys = []; // "product_id:variant_id"

        foreach ($memberItems as $item) {
            if ($item->subscription_plan_id) {
                $memberSubscriptionPlanIds[$item->subscription_plan_id] = true;
            } elseif ($item->product_id) {
                $key = $item->product_id . ':' . ($item->variant_id ?? '');
                $memberProductKeys[$key] = true;
            }
        }

        $migrated = 0;

        foreach ($sessionItems as $item) {
            // Skip items already owned by a member (they were loaded above too).
            if (!empty($item->user_id)) {
                continue;
            }

            // Conflict check for subscriptions.
            if ($item->subscription_plan_id) {
                if (isset($memberSubscriptionPlanIds[$item->subscription_plan_id])) {
                    $this->cartRepository->delete($item->id);
                    continue;
                }
                $memberSubscriptionPlanIds[$item->subscription_plan_id] = true;
            } elseif ($item->product_id) {
                $key = $item->product_id . ':' . ($item->variant_id ?? '');
                if (isset($memberProductKeys[$key])) {
                    $this->cartRepository->delete($item->id);
                    continue;
                }
                $memberProductKeys[$key] = true;
            }

            $this->cartRepository->update($item->id, ['user_id' => $memberId]);
            $migrated++;
        }

        return $migrated;
    }
}