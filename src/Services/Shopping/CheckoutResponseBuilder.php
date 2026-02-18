<?php

namespace App\Services\Shopping;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Order;

class CheckoutResponseBuilder
{
    public function buildCheckoutResponse(
        Order $order,
        array $subscriptions,
        array $paymentResult
    ): array
    {
        $subscriptionIds = array_map(fn($s) => $s['subscription']->id, $subscriptions);

        $response = [
            'success' => true,
            'client_secret' => $paymentResult['client_secret'],
            'payment_intent_id' => $paymentResult['payment_intent_id'],
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'requires_shipping' => $this->hasAnyPrintDelivery($subscriptions),
        ];

        // Add subscription data based on count
        if (count($subscriptionIds) === 1) {
            $response['subscription_id'] = $subscriptionIds[0];
        } else {
            $response['subscription_ids'] = $subscriptionIds;
            $response['multiple_subscriptions'] = true;
        }

        return $response;
    }

    private function hasAnyPrintDelivery(array $subscriptions): bool
    {
        foreach ($subscriptions as $subData) {
            if ($subData['pricing']->deliveryType === SubscriptionType::PRINTED->value) {
                return true;
            }
        }
        return false;
    }
}