<?php

namespace App\Services\Subscriptions\Communications;

use App\Models\Subscription;
use App\Models\SubscriptionPricingChange;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;

class SubscriptionItdCommunicationService
{
    private const PRICE_RISE_COMMUNICATION_KEY = 'itd_price_rise_default';

    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationSender     $sender,
    ) {
    }

    public function generateForPriceRise(
        SubscriptionPricingChange $pricingChange,
        Subscription              $oldSubscription,
        Subscription              $newSubscription,
        int                       $transitionId,
        string                    $letterCode,
    ): void {
        $communication = $this->communications->findActiveByKey(self::PRICE_RISE_COMMUNICATION_KEY);

        if (!$communication) {
            throw new \RuntimeException(
                sprintf('Active ITD communication [%s] was not found.', self::PRICE_RISE_COMMUNICATION_KEY)
            );
        }

        $dedupeKey = sprintf(
            'pricing-change:%d:transition:%d:itd',
            $pricingChange->id,
            $transitionId
        );

        $this->sender->send(
            subscription: $newSubscription,
            communication: $communication,
            schedule: null,
            metadata: [
                'letter_code' => $letterCode,
                'pricing_change_id' => $pricingChange->id,
                'transition_id' => $transitionId,
                'old_subscription_id' => $oldSubscription->id,
                'new_subscription_id' => $newSubscription->id,
                'old_plan_id' => $oldSubscription->plan_id,
                'new_plan_id' => $newSubscription->plan_id,
                'old_price' => $pricingChange->old_price,
                'new_price' => $pricingChange->new_price,
                'currency' => $pricingChange->currency,
                'effective_date' => $pricingChange->effective_date?->format('Y-m-d'),
            ],
            dedupeKey: $dedupeKey,
        );
    }
}