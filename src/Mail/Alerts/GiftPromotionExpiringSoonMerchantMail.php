<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\GiftPromotion;
use App\Models\Merchant;

/**
 * Sent to the merchant who owns a gift promotion that is approaching expiry.
 *
 * Carries promotion-specific context: gift type, quantity rule, and
 * the number of configured triggers so the merchant can judge whether
 * to extend or let it lapse.
 */
class GiftPromotionExpiringSoonMerchantMail extends Mailable
{
    public function __construct(
        private readonly Merchant             $merchant,
        private readonly GiftPromotion        $promotion,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $promotionName = $this->promotion->name ?? "Promotion #{$this->promotion->id}";

        return $this
            ->subject("Your promotion is expiring in {$this->threshold->value} hours — {$promotionName}")
            ->markdown('emails.alerts.gift-promotion-expiring-soon-merchant', [
                'merchant' => $this->merchant,
                'promotion' => $this->promotion,
                'promotionName' => $promotionName,
                'giftType' => ucfirst($this->promotion->gift_type),
                'quantityRule' => ucfirst(str_replace('_', ' ', $this->promotion->quantity_rule)),
                'triggerCount' => $this->promotion->triggers(true)->count(),
                'expiresAt' => $this->promotion->ends_at,
                'hoursRemaining' => $this->threshold->value,
                'manageUrl' => $this->buildManageUrl(),
            ]);
    }

    private function buildManageUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/merchant/promotions/' . $this->promotion->id;
    }
}