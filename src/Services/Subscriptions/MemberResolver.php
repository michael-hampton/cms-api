<?php

namespace App\Services\Subscriptions;

use App\Models\Member;
use App\Models\Model;
use App\Repositories\Members\MemberRepository;

/**
 * Resolves the owner Member for a subscription item.
 *
 * Non-gift items → buyer Member (no DB work).
 * Gift items     → find-or-create recipient Member by email.
 *
 * This class owns the single path for Member resolution. No caller may
 * bypass it to look up or create Members directly.
 */
class MemberResolver
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
    )
    {
    }

    /**
     * Return the Member who will own the subscription.
     *
     * Reads gift fields from $itemData:
     *   gift_email       (string|null) — triggers gift resolution when present
     *   gift_first_name  (string|null)
     *   gift_last_name   (string|null)
     *   gift_mobile      (string|null)
     *
     * When gift_email is absent or empty, $buyer is returned as-is (zero
     * database work, fully backward-compatible with non-gift checkouts).
     */
    public function resolve(array $itemData, Member $buyer): Model
    {
        $giftEmail = $itemData['gift_email'] ?? null;

        if (empty($giftEmail)) {
            return $buyer;
        }

        $existing = $this->memberRepository->findByEmail($giftEmail);

        if ($existing) {
            return $existing;
        }

        return $this->memberRepository->create([
            'first_name' => $itemData['gift_first_name'] ?? null,
            'last_name' => $itemData['gift_last_name'] ?? null,
            'email' => $giftEmail,
            'mobile' => $itemData['gift_mobile'] ?? null,
            'password' => null, // activates the account-setup / password-reset flow
            'site_id' => $itemData['site_id'] ?? null,
        ]);
    }
}