<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\GiftPromotion;
use App\Models\Member;

/**
 * Sent to active members of a merchant when one of that merchant's gift
 * promotions is approaching expiry.
 *
 * Member recipients are all active members associated with the merchant
 * (i.e. members who have previously transacted with them), resolved by
 * OfferExpiryAlertRepository::memberIdsForMerchant().
 *
 * Kept intentionally lightweight — we do not expose internal trigger
 * conditions to members. The call-to-action is to shop before the
 * promotion ends.
 */
class GiftPromotionExpiringSoonMemberMail extends Mailable
{
    public function __construct(
        private readonly Member               $member,
        private readonly GiftPromotion        $promotion,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $promotionName = $this->promotion->name ?? 'a special promotion';

        return $this
            ->to($this->member->email)
            ->subject("Don't miss out — special offer ending in {$this->threshold->value} hours")
            ->markdown('emails.alerts.gift-promotion-expiring-soon-member', [
                'member' => $this->member,
                'promotion' => $this->promotion,
                'promotionName' => $promotionName,
                'giftType' => ucfirst($this->promotion->gift_type),
                'expiresAt' => $this->promotion->ends_at,
                'hoursRemaining' => $this->threshold->value,
                'shopUrl' => $this->buildShopUrl(),
            ]);
    }

    private function buildShopUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/shop';
    }
}