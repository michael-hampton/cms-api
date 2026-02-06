<?php

namespace App\Services\Subscriptions\Validators;

use App\Enums\Subscriptions\BillingPeriod;
use App\Exceptions\Subscriptions\InvalidDeliveryTypeException;
use App\Exceptions\Subscriptions\InvalidSubscriptionPlanException;
use App\Models\SubscriptionPlan;

class OneTimePlanValidator
{
    public function validatePlanForSubscription(
        ?SubscriptionPlan $plan,
        string            $deliveryType
    ): void
    {
        if (!$plan || !$plan->isOneTime()) {
            throw new InvalidSubscriptionPlanException('Invalid one-time subscription plan');
        }

        $this->validateDeliveryType($plan, $deliveryType);
    }

    private function validateDeliveryType(SubscriptionPlan $plan, string $deliveryType): void
    {
        if ($deliveryType === 'digital' && !$plan->hasDigitalOption()) {
            throw new InvalidDeliveryTypeException('Digital delivery not available for this plan');
        }

        if ($deliveryType === 'print' && !$plan->hasPrintOption()) {
            throw new InvalidDeliveryTypeException('Print delivery not available for this plan');
        }
    }

    public function validateBillingPeriod(string $period): BillingPeriod
    {
        return BillingPeriod::tryFrom($period)
            ?? throw new InvalidSubscriptionPlanException("Invalid billing period: {$period}");
    }
}