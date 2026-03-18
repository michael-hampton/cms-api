<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\Merchant;
use App\Models\ProductOffer;

/**
 * Sent to the merchant who owns an offer that is approaching its expiry.
 *
 * Carries offer-specific fields (product name, sale price, original price,
 * discount percentage) so the merchant has enough context to act without
 * clicking through to the dashboard.
 */
class OfferExpiringSoonMerchantMail extends Mailable
{
    public function __construct(
        private readonly Merchant             $merchant,
        private readonly ProductOffer         $offer,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $productName = $this->offer->product->name ?? "Offer #{$this->offer->id}";

        return $this
            ->subject("Your offer is expiring in {$this->threshold->value} hours — {$productName}")
            ->markdown('emails.alerts.offer-expiring-soon-merchant', [
                'merchant' => $this->merchant,
                'offer' => $this->offer,
                'productName' => $productName,
                'salePrice' => $this->offer->sale_price,
                'originalPrice' => $this->offer->original_price,
                'discountPercentage' => $this->offer->discount_percentage,
                'expiresAt' => $this->offer->end_date,
                'hoursRemaining' => $this->threshold->value,
                'manageUrl' => $this->buildManageUrl(),
            ]);
    }

    private function buildManageUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/merchant/offers/' . $this->offer->id;
    }
}