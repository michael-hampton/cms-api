<?php

namespace App\Services\Billing\Payments;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\Models\Member;
use App\Models\Order;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;

/**
 * Creates Stripe PaymentIntents for orders.
 *
 * Replaces the direct StripePaymentProcessor call.
 * Customer resolution is handled by StripeCustomerGateway.
 */
class PaymentIntentService
{
    public function __construct(
        private readonly StripePaymentIntentGateway $paymentIntentGateway,
        private readonly StripeCustomerGateway      $customerGateway,
    ) {}

    /**
     * Create a PaymentIntent for a pending order.
     * Called OUTSIDE the database transaction.
     */
    public function createForOrder(
        Order  $order,
        array  $subscriptions,
        Member $member,
        int    $siteId,
    ): array {
        $subscriptionIds = array_map(fn ($s) => $s['subscription']->id, $subscriptions);

        $customerId = $this->customerGateway->getOrCreate($member);

        $dto = new CreatePaymentIntentDto(
            amountCents:      (int) round($order->total * 100),
            currency:         strtolower($order->currency),
            metadata:         [
                'order_id'                => $order->id,
                'subscription_count'      => count($subscriptions),
                'subscription_ids'        => implode(',', $subscriptionIds),
                'member_id'               => $member->id,
                'multiple_subscriptions'  => count($subscriptionIds) > 1,
            ],
            stripeCustomerId: $customerId,
        );

        $result = $this->paymentIntentGateway->createWithCustomer($dto);

        return $result->toLegacyArray();
    }
}