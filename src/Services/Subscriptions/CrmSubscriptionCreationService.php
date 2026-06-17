<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Checkout\CheckoutException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Session\Session;
use App\Framework\Support\Logger;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use Exception;
use InvalidArgumentException;

class CrmSubscriptionCreationService
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CartService $cartService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly MemberAuthWrapper $memberAuth,
        private readonly SubscriptionPaymentService $subscriptionPaymentService,
        private readonly AddressRepository $addressRepository,
    ) {
    }

    /**
     * Creates either a one-off or recurring subscription using the same checkout
     * path as the storefront. The plan is the source of truth for which Stripe
     * operation is performed.
     *
     * @throws InvalidArgumentException
     * @throws CheckoutException
     */
    public function createSubscription(
        int $memberId,
        int $planId,
        string $paymentMethodId,
        int $siteId,
        ?int $deliveryAddressId = null,
        ?array $deliveryAddress = null,
        ?int $pricingId = null,
        ?string $offerType = null,
        array $giftData = [],
    ): array {
        $member = $this->memberRepository->find($memberId);
        if (!$member) {
            throw new InvalidArgumentException('Member not found.');
        }
        if ((int)$member->site_id !== $siteId) {
            throw new InvalidArgumentException('Member does not belong to this site.');
        }

        $plan = $this->planRepository->find($planId);
        if (!$plan) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }
        if (!$plan->is_active) {
            throw new InvalidArgumentException("Plan \"{$plan->name}\" is not currently active.");
        }
        if ((int)$plan->site_id !== $siteId) {
            throw new InvalidArgumentException('Plan does not belong to this site.');
        }
        if ($this->subscriptionRepository->hasActiveSubscriptionToPlan($memberId, $planId)) {
            throw new InvalidArgumentException('Member already has an active subscription on this plan.');
        }

        if ($deliveryAddressId === null && !empty($deliveryAddress)) {
            $createdAddress = $this->addressRepository->createAddressForMember($memberId, $deliveryAddress, $siteId);
            $deliveryAddressId = (int)$createdAddress->id;
        }

        $isOneTime = $plan->isOneTime();
        $this->memberAuth->login($member);
        Session::put('member_id', $memberId);

        try {
            $this->cartService->addSubscriptionToCart(
                $planId,
                $plan->getDeliveryOptions()[0] ?? 'digital',
                array_filter([
                    'pricing_tier_id' => $pricingId,
                    'offer_type' => $offerType,
                ], static fn(mixed $value): bool => $value !== null),
            );

            $giftFields = !empty($giftData['is_gift']) ? array_filter([
                'is_gift' => true,
                'recipient_email' => $giftData['recipient_email'] ?? null,
                'recipient_first_name' => $giftData['recipient_first_name'] ?? null,
                'recipient_last_name' => $giftData['recipient_last_name'] ?? null,
            ], static fn(mixed $value): bool => $value !== null && $value !== '') : [];

            $result = $this->checkoutService->processCheckout(array_merge([
                'payment_method_id' => $paymentMethodId,
                'one_time_subscription' => $isOneTime,
                'admin_created' => true,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone ?? '',
                'saved_address' => $deliveryAddressId,
            ], $giftFields), $siteId);

            $subscription = $this->resolveSubscription($result);
            if (!$subscription) {
                throw new CheckoutException('Checkout did not return a subscription.');
            }

            if (!$isOneTime) {
                $paymentResult = $this->subscriptionPaymentService->processStripeSubscriptionPayment(
                    $subscription,
                    $subscription->plan,
                    [
                        'payment_method_id' => $paymentMethodId,
                        'order_id' => $result['order_id'],
                        'pricing_tier_id' => $pricingId,
                    ],
                );

                $updates = ['payment_subscription_id' => $paymentResult['subscription_id']];
                if (!empty($paymentResult['stripe_subscription_item_id'])) {
                    $updates['stripe_subscription_item_id'] = $paymentResult['stripe_subscription_item_id'];
                }
                $subscription->update($updates);
            }
        } catch (Exception $exception) {
            Logger::info('Failed to create subscription for member', [
                'member_id' => $memberId,
                'plan_id' => $planId,
                'one_time' => $isOneTime,
            ]);
            throw $exception;
        } finally {
            $this->cartService->clear();
        }

        Logger::info('Admin created subscription for member', [
            'member_id' => $memberId,
            'plan_id' => $planId,
            'subscription_id' => $subscription->id,
            'one_time' => $isOneTime,
        ]);

        return ['success' => true, 'subscription' => $subscription];
    }

    private function resolveSubscription(array $checkoutResult): ?object
    {
        $ids = $checkoutResult['subscription_ids']
            ?? (!empty($checkoutResult['subscription_id']) ? [$checkoutResult['subscription_id']] : []);

        return $ids === [] ? null : $this->subscriptionRepository->find((int)$ids[0]);
    }
}
