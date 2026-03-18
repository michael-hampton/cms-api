<?php

declare(strict_types=1);

namespace App\Mail\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Mail\Mailable;
use App\Models\Merchant;

/**
 * Sent to a merchant when one of their offers, bundles, or gift promotions
 * is approaching its expiry date.
 *
 * Member-facing expiry alerts reuse the existing OfferEndingSoon mailable.
 */
class OfferExpiryAlertMerchantMail extends Mailable
{
    public function __construct(
        private readonly Merchant             $merchant,
        private readonly AlertableEntityType  $entityType,
        private readonly int                  $entityId,
        private readonly string               $entityName,
        private readonly \DateTimeInterface   $expiresAt,
        private readonly ExpiryAlertThreshold $threshold,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $hoursRemaining = $this->threshold->value;
        $typeLabel = $this->entityTypeLabel();

        return $this
            ->to('test@example.com') //todo
            ->subject("Your {$typeLabel} is expiring in {$hoursRemaining} hours — {$this->entityName}")
            ->markdown('emails.alerts.offer-expiry-merchant', [
                'merchant' => $this->merchant,
                'entityType' => $this->entityType,
                'entityTypeLabel' => $typeLabel,
                'entityId' => $this->entityId,
                'entityName' => $this->entityName,
                'expiresAt' => $this->expiresAt,
                'hoursRemaining' => $hoursRemaining,
                'manageUrl' => $this->buildManageUrl(),
            ]);
    }

    private function entityTypeLabel(): string
    {
        return match ($this->entityType) {
            AlertableEntityType::ProductOffer => 'offer',
            AlertableEntityType::ProductOfferBundle => 'bundle',
            AlertableEntityType::GiftPromotion => 'promotion',
        };
    }

    private function buildManageUrl(): string
    {
        $base = rtrim(config('app.url'), '/');

        return match ($this->entityType) {
            AlertableEntityType::ProductOffer => "{$base}/merchant/offers/{$this->entityId}",
            AlertableEntityType::ProductOfferBundle => "{$base}/merchant/bundles/{$this->entityId}",
            AlertableEntityType::GiftPromotion => "{$base}/merchant/promotions/{$this->entityId}",
        };
    }
}