<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

/**
 * Fully-resolved (no nulls left) save options for one cancellation
 * reason, after CancellationOptionsResolver has walked the
 * product -> brand -> default inheritance chain field-by-field.
 */
final class ResolvedCancellationOptions
{
    public function __construct(
        public readonly bool $showSaveActions,
        public readonly bool $allowDiscount,
        public readonly bool $allowOfferSwitch,
        public readonly bool $allowCancel,
        public readonly int $refundMaxPercent,
        public readonly bool $marketingConsent,
    ) {
    }

    public function toArray(): array
    {
        return [
            'show_save_actions' => $this->showSaveActions,
            'allow_discount' => $this->allowDiscount,
            'allow_offer_switch' => $this->allowOfferSwitch,
            'allow_cancel' => $this->allowCancel,
            'refund_max_percent' => $this->refundMaxPercent,
            'marketing_consent' => $this->marketingConsent,
        ];
    }
}
