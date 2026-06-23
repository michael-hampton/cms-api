<?php

namespace App\Services\Billing\Payments;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\Models\Member;
use App\Models\Order;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;

class PaymentIntentService
{
    private const CHECKOUT_HASH_METADATA_KEY = 'checkout_hash';

    private const REUSABLE_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
    ];

    private const EDITABLE_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
    ];

    public function __construct(
        private readonly StripePaymentIntentGateway $paymentIntentGateway,
        private readonly StripeCustomerGateway      $customerGateway,
    ) {}

    public function createForOrder(
        Order  $order,
        array  $subscriptions,
        Member $member,
        int    $siteId,
    ): array {
        $subscriptionIds = array_map(fn ($s) => $s['subscription']->id, $subscriptions);
        $customerId = $this->customerGateway->getOrCreate($member);
        $dto = $this->makeOrderPaymentIntentDto($order, $subscriptions, $member, $siteId, $customerId, $subscriptionIds);

        $existingPaymentIntentId = $order->payment_intent_id ?? null;

        if (!$existingPaymentIntentId) {
            return $this->paymentIntentGateway->createWithCustomer($dto)->toLegacyArray();
        }

        $existing = $this->paymentIntentGateway->retrieve($existingPaymentIntentId);

        if (!$existing->success || !$this->isReusable($existing)) {
            return $this->paymentIntentGateway->createWithCustomer($dto)->toLegacyArray();
        }

        if ($this->matchesOrderState($existing, $dto)) {
            return $existing->toLegacyArray();
        }

        if ($this->canBeUpdated($existing, $dto)) {
            return $this->paymentIntentGateway->update($existingPaymentIntentId, $dto)->toLegacyArray();
        }

        return $this->paymentIntentGateway->createWithCustomer($dto)->toLegacyArray();
    }

    private function makeOrderPaymentIntentDto(
        Order $order,
        array $subscriptions,
        Member $member,
        int $siteId,
        string $customerId,
        array $subscriptionIds,
    ): CreatePaymentIntentDto {
        $metadata = [
            'order_id'               => $order->id,
            'subscription_count'     => count($subscriptions),
            'subscription_ids'       => implode(',', $subscriptionIds),
            'member_id'              => $member->id,
            'multiple_subscriptions' => count($subscriptionIds) > 1,
            'site_id'                => $siteId,
        ];

        $metadata[self::CHECKOUT_HASH_METADATA_KEY] = $this->buildCheckoutHash($order, $metadata);

        return new CreatePaymentIntentDto(
            amountCents:      (int) round($order->total * 100),
            currency:         strtolower($order->currency),
            metadata:         $metadata,
            stripeCustomerId: $customerId,
        );
    }

    private function matchesOrderState(PaymentIntentResultDto $intent, CreatePaymentIntentDto $dto): bool
    {
        return $intent->amountCents === $dto->amountCents
            && strtolower((string) $intent->currency) === strtolower($dto->currency)
            && ($intent->metadata[self::CHECKOUT_HASH_METADATA_KEY] ?? null) === ($dto->metadata[self::CHECKOUT_HASH_METADATA_KEY] ?? null);
    }

    private function isReusable(PaymentIntentResultDto $intent): bool
    {
        return in_array($intent->status, self::REUSABLE_STATUSES, true);
    }

    private function canBeUpdated(PaymentIntentResultDto $intent, CreatePaymentIntentDto $dto): bool
    {
        return in_array($intent->status, self::EDITABLE_STATUSES, true)
            && strtolower((string) $intent->currency) === strtolower($dto->currency);
    }

    private function buildCheckoutHash(Order $order, array $metadata): string
    {
        $payload = [
            'order_id' => $order->id,
            'total' => (int) round($order->total * 100),
            'currency' => strtolower((string) $order->currency),
            'metadata' => $metadata,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
