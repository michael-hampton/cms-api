<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\ProductOfferBundle;

/**
 * Sent to a member who wishlisted a bundle that is approaching its expiry.
 *
 * Distinct from OfferExpiryAlertMerchantMail (which targets the merchant
 * who owns the entity) and OfferEndingSoon (which is offer-specific and
 * carries a ProductOffer model).
 */
class BundleExpiringSoonMemberMail extends Mailable
{
    public function __construct(
        private readonly Member               $member,
        private readonly ProductOfferBundle   $bundle,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Don't miss out — bundle ending in {$this->threshold->value} hours: {$this->bundle->name}")
            ->markdown('emails.alerts.bundle-expiring-soon-member', [
                'member' => $this->member,
                'bundle' => $this->bundle,
                'hoursRemaining' => $this->threshold->value,
                'expiresAt' => $this->bundle->end_date,
                'bundleUrl' => $this->buildBundleUrl(),
            ]);
    }

    private function buildBundleUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/bundles/' . $this->bundle->slug;
    }
}