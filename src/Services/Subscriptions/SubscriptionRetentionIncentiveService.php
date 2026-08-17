<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeCouponGateway;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Vouchers\VoucherService;
use InvalidArgumentException;
use RuntimeException;
use Stripe\StripeClient;

final class SubscriptionRetentionIncentiveService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPlanPricingRepository $pricingRepository,
        private readonly VoucherService $voucherService,
        private readonly StripeCouponGateway $couponGateway,
        private readonly StripeSubscriptionPlanUpdater $planUpdater,
        private readonly Database $database,
        private readonly StripeClient $stripe,
    ) {}

    public function applyOffer(
        int $subscriptionId,
        int $pricingId,
        string $offerType,
        ?string $reason = null,
    ): object {
        $subscription = $this->requireActiveSubscription($subscriptionId);
        $pricing = $this->pricingRepository->find($pricingId);

        if (!$pricing instanceof SubscriptionPlanPricing || !$pricing->is_active) {
            throw new InvalidArgumentException('Selected offer is no longer available.');
        }

        if ((int)$pricing->plan_id !== (int)$subscription->plan_id) {
            throw new InvalidArgumentException('Selected offer does not belong to this subscription plan.');
        }

        if (!in_array($offerType, ['print', 'digital'], true)) {
            throw new InvalidArgumentException('Only standard print or digital offers can be used for retention.');
        }

        $stripePriceId = (string)($pricing->stripe_price_id ?? '');

        if ($stripePriceId === '') {
            throw new InvalidArgumentException('Selected offer has not been synchronised with Stripe.');
        }

        $stripeSubscriptionId = $this->stripeSubscriptionId($subscription);
        $stripeItemId = $subscription->stripe_subscription_item_id
            ?? $this->planUpdater->findFirstSubscriptionItemId($stripeSubscriptionId);

        if (!$stripeItemId) {
            throw new RuntimeException('Unable to resolve the Stripe subscription item.');
        }

        $price = $offerType === 'digital'
            ? $pricing->getEffectiveDigitalPrice()
            : $pricing->getEffectivePrintPrice();

        $result = $this->planUpdater->updateSubscriptionItemPrice(
            $stripeItemId,
            $stripePriceId,
            ['proration_behavior' => 'none'],
        );

        if (($result['success'] ?? false) !== true) {
            throw new RuntimeException((string)($result['error'] ?? 'Failed to apply retention offer in Stripe.'));
        }

        return $this->database->transaction(function () use (
            $subscription,
            $pricing,
            $offerType,
            $stripePriceId,
            $stripeItemId,
            $price,
        ) {
            $this->subscriptionRepository->update((int)$subscription->id, [
                'subscription_plan_pricing_id' => (int)$pricing->id,
                'offer_type' => $offerType,
                'price' => $price,
                'price_paid_cents' => (int)round($price * 100),
                'stripe_price_id' => $stripePriceId,
                'stripe_subscription_item_id' => $stripeItemId,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'auto_renew' => true,
            ]);

            return $this->subscriptionRepository->find((int)$subscription->id);
        });
    }

    public function applyVoucher(
        int $subscriptionId,
        string $voucherCode,
        ?string $reason = null,
    ): object {
        $subscription = $this->requireActiveSubscription($subscriptionId);

        $validation = $this->voucherService->validateVoucherForSubscription(
            code: $voucherCode,
            planId: (int)$subscription->plan_id,
            userId: (int)$subscription->member_id,
            pricingTierId: !empty($subscription->subscription_plan_pricing_id)
                ? (int)$subscription->subscription_plan_pricing_id
                : null,
            deliveryType: $subscription->delivery_type ?? null,
        );

        if (!$validation->valid || !$validation->voucher) {
            throw new InvalidArgumentException($validation->message);
        }

        $coupon = $this->couponGateway->getOrCreateForVoucher(
            (int)$validation->voucher->id,
            strtolower((string)($subscription->currency ?? 'gbp')),
        );

        $stripeSubscriptionId = $this->stripeSubscriptionId($subscription);
        $metadata = [
            'retention_voucher_id' => (string)$validation->voucher->id,
            'retention_voucher_code' => (string)$validation->voucher->code,
        ];

        if ($reason !== null && $reason !== '') {
            $metadata['retention_reason'] = mb_substr($reason, 0, 500);
        }

        $this->stripe->subscriptions->update($stripeSubscriptionId, [
            'discounts' => [['coupon' => $coupon['coupon_id']]],
            'metadata' => $metadata,
        ]);

        return $this->database->transaction(function () use ($subscription, $validation): object {
            $this->voucherService->applyVoucher(
                voucherId: (int)$validation->voucher->id,
                userId: (int)$subscription->member_id,
                discountAmount: (float)$validation->discount,
            );

            $this->subscriptionRepository->update((int)$subscription->id, [
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'auto_renew' => true,
            ]);

            return $this->subscriptionRepository->find((int)$subscription->id);
        });
    }

    private function requireActiveSubscription(int $subscriptionId): object
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        if (!in_array($subscription->status, ['active', 'trialing', 'past_due'], true)) {
            throw new InvalidArgumentException('Retention incentives can only be applied to a live subscription.');
        }

        return $subscription;
    }

    private function stripeSubscriptionId(object $subscription): string
    {
        $id = $subscription->stripe_subscription_id
            ?? $subscription->payment_subscription_id
            ?? null;

        if (!$id) {
            throw new InvalidArgumentException('Subscription is not linked to Stripe.');
        }

        return (string)$id;
    }
}
