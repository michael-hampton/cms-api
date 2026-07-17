<?php

namespace App\Listeners\Billing;

use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Events\Billing\SubscriptionPaymentMethodChanged;
use App\Framework\Support\Logger;

/**
 * Records saved payment method lifecycle actions for analytics/audit,
 * regardless of whether the action originated from the PressStack
 * account area or the site-scoped member area.
 */
class LogPaymentMethodAnalyticsListener
{
    public function handleAdded(PaymentMethodAdded $event): void
    {
        Logger::info('Payment method added', [
            'member_id' => $event->memberId,
            'payment_method_id' => $event->paymentMethodId,
            'set_as_default' => $event->setAsDefault,
            'source' => $event->source,
        ]);

        // TODO: push to analytics pipeline once one is agreed for billing events.
    }

    public function handleRemoved(PaymentMethodRemoved $event): void
    {
        Logger::info('Payment method removed', [
            'member_id' => $event->memberId,
            'payment_method_id' => $event->paymentMethodId,
            'source' => $event->source,
        ]);

        // TODO: push to analytics pipeline once one is agreed for billing events.
    }

    public function handleDefaultChanged(DefaultPaymentMethodChanged $event): void
    {
        Logger::info('Default payment method changed', [
            'member_id' => $event->memberId,
            'payment_method_id' => $event->paymentMethodId,
            'source' => $event->source,
        ]);

        // TODO: push to analytics pipeline once one is agreed for billing events.
    }

    public function handleSubscriptionPaymentMethodChanged(SubscriptionPaymentMethodChanged $event): void
    {
        Logger::info('Subscription payment method changed', [
            'member_id' => $event->memberId,
            'subscription_id' => $event->subscriptionId,
            'payment_method_id' => $event->paymentMethodId,
            'source' => $event->source,
        ]);

        // TODO: push to analytics pipeline once one is agreed for billing events.
    }
}