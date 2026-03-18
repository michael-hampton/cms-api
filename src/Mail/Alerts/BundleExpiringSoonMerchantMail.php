<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\Merchant;
use App\Models\ProductOfferBundle;

/**
 * Sent to the merchant who owns a bundle that is approaching its expiry.
 *
 * Carries bundle-specific fields (bundle name, bundle price, total value,
 * discount percentage, item count) so the merchant has full context to
 * decide whether to extend or let the bundle lapse.
 *
 * Recipient is set by the service via $mailer->to($contact->email)->send().
 * build() does NOT call ->to() — merchants have no email field directly.
 */
class BundleExpiringSoonMerchantMail extends Mailable
{
    public function __construct(
        private readonly Merchant             $merchant,
        private readonly ProductOfferBundle   $bundle,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject("Your bundle is expiring in {$this->threshold->value} hours — {$this->bundle->name}")
            ->markdown('emails.alerts.bundle-expiring-soon-merchant', [
                'merchant' => $this->merchant,
                'bundle' => $this->bundle,
                'bundleName' => $this->bundle->name,
                'bundlePrice' => $this->bundle->bundle_price,
                'totalValue' => $this->bundle->total_price,
                'discountPercentage' => $this->bundle->discount_percentage,
                'itemCount' => $this->bundle->items(true)->count(),
                'expiresAt' => $this->bundle->end_date,
                'hoursRemaining' => $this->threshold->value,
                'manageUrl' => $this->buildManageUrl(),
            ]);
    }

    private function buildManageUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/merchant/bundles/' . $this->bundle->id;
    }
}